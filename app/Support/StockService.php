<?php

namespace App\Support;

use App\Models\Item;
use App\Models\ItemStock;
use App\Models\StockMutation;
use Illuminate\Validation\ValidationException;
use App\Support\WarehouseService;

class StockService
{
    public static function mutate(array $payload): void
    {
        $itemId = (int) ($payload['item_id'] ?? 0);
        $direction = $payload['direction'] ?? 'in';
        $qty = (int) ($payload['qty'] ?? 0);
        $warehouseId = (int) ($payload['warehouse_id'] ?? 0);
        if ($warehouseId <= 0) {
            $warehouseId = WarehouseService::defaultWarehouseId();
        }

        if ($itemId <= 0 || $qty <= 0) {
            throw ValidationException::withMessages([
                'qty' => 'Qty tidak valid',
            ]);
        }

        $item = Item::query()->find($itemId);
        if (!$item) {
            throw ValidationException::withMessages([
                'item_id' => 'Item tidak ditemukan',
            ]);
        }
        if ($item->isBundle()) {
            throw ValidationException::withMessages([
                'item_id' => 'SKU bundle tidak memiliki stok fisik dan tidak boleh dimutasi langsung.',
            ]);
        }

        $stock = ItemStock::where('item_id', $itemId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->first();
        if (!$stock) {
            $stock = ItemStock::create([
                'item_id' => $itemId,
                'warehouse_id' => $warehouseId,
                'stock' => 0,
            ]);
        }

        if ($direction === 'out' && $stock->stock < $qty) {
            throw ValidationException::withMessages([
                'qty' => 'Stok tidak mencukupi',
            ]);
        }

        $stock->stock = $direction === 'out'
            ? ($stock->stock - $qty)
            : ($stock->stock + $qty);
        $stock->save();

        $sourceType = $payload['source_type'] ?? null;
        $sourceId = $payload['source_id'] ?? null;
        if (!$sourceType || !$sourceId) {
            throw ValidationException::withMessages([
                'source' => 'Sumber mutasi tidak valid',
            ]);
        }

        StockMutation::create([
            'item_id' => $itemId,
            'reference_item_id' => $payload['reference_item_id'] ?? $itemId,
            'reference_sku' => $payload['reference_sku'] ?? $item->sku,
            'warehouse_id' => $warehouseId,
            'direction' => $direction,
            'qty' => $qty,
            'source_type' => $sourceType,
            'source_subtype' => $payload['source_subtype'] ?? null,
            'source_id' => $sourceId,
            'source_code' => $payload['source_code'] ?? null,
            'note' => $payload['note'] ?? null,
            'occurred_at' => $payload['occurred_at'] ?? now(),
            'created_by' => $payload['created_by'] ?? null,
        ]);
    }

    public static function rollbackBySource(string $sourceType, int $sourceId): void
    {
        $mutations = StockMutation::where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->orderByDesc('id')
            ->get();

        foreach ($mutations as $mutation) {
            $warehouseId = (int) ($mutation->warehouse_id ?? 0);
            if ($warehouseId <= 0) {
                $warehouseId = WarehouseService::defaultWarehouseId();
            }
            $stock = ItemStock::where('item_id', $mutation->item_id)
                ->where('warehouse_id', $warehouseId)
                ->lockForUpdate()
                ->first();
            if (!$stock) {
                $stock = ItemStock::create([
                    'item_id' => $mutation->item_id,
                    'warehouse_id' => $warehouseId,
                    'stock' => 0,
                ]);
            }

            if ($mutation->direction === 'in') {
                $newStock = $stock->stock - $mutation->qty;
            } else {
                $newStock = $stock->stock + $mutation->qty;
            }

            if ($newStock < 0) {
                throw ValidationException::withMessages([
                    'qty' => 'Stok tidak mencukupi untuk rollback',
                ]);
            }

            $stock->stock = $newStock;
            $stock->save();
        }
    }

    public static function currentQty(int $itemId, int $warehouseId): int
    {
        return (int) (ItemStock::query()
            ->where('item_id', $itemId)
            ->where('warehouse_id', $warehouseId)
            ->value('stock') ?? 0);
    }

    public static function assertSellableAvailable(iterable $rows, int $warehouseId): void
    {
        $requirements = BundleService::expandItemRows($rows);
        if (empty($requirements)) {
            return;
        }

        $itemIds = collect($requirements)->pluck('item_id')->unique()->values()->all();
        $stocks = ItemStock::query()
            ->where('warehouse_id', $warehouseId)
            ->whereIn('item_id', $itemIds)
            ->get(['item_id', 'stock'])
            ->keyBy('item_id');

        foreach ($requirements as $requirement) {
            $available = (int) ($stocks->get((int) $requirement['item_id'])?->stock ?? 0);
            $required = (int) $requirement['qty'];
            if ($available < $required) {
                throw ValidationException::withMessages([
                    'qty' => "Stok tidak mencukupi untuk SKU {$requirement['sku']}. Tersedia {$available}, dibutuhkan {$required}.",
                ]);
            }
        }
    }

    public static function depleteSellableRows(iterable $rows, int $warehouseId, array $context = []): void
    {
        $requirements = BundleService::expandItemRows($rows);
        if (empty($requirements)) {
            return;
        }

        $itemIds = collect($requirements)->pluck('item_id')->unique()->sort()->values()->all();
        $stocks = ItemStock::query()
            ->where('warehouse_id', $warehouseId)
            ->whereIn('item_id', $itemIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('item_id');

        foreach ($itemIds as $itemId) {
            if (!$stocks->has($itemId)) {
                $stocks->put($itemId, ItemStock::create([
                    'item_id' => $itemId,
                    'warehouse_id' => $warehouseId,
                    'stock' => 0,
                ]));
            }
        }

        foreach ($requirements as $requirement) {
            $stock = $stocks->get((int) $requirement['item_id']);
            $qty = (int) $requirement['qty'];
            if ((int) $stock->stock < $qty) {
                throw ValidationException::withMessages([
                    'qty' => "Stok tidak mencukupi untuk SKU {$requirement['sku']}.",
                ]);
            }
        }

        foreach ($requirements as $requirement) {
            self::mutate([
                'item_id' => (int) $requirement['item_id'],
                'reference_item_id' => (int) ($requirement['reference_item_id'] ?? $requirement['item_id']),
                'reference_sku' => $requirement['reference_sku'] ?? $requirement['sku'],
                'warehouse_id' => $warehouseId,
                'direction' => 'out',
                'qty' => (int) $requirement['qty'],
                'source_type' => $context['source_type'] ?? 'outbound',
                'source_subtype' => $context['source_subtype'] ?? null,
                'source_id' => $context['source_id'] ?? null,
                'source_code' => $context['source_code'] ?? null,
                'note' => $context['note'] ?? null,
                'occurred_at' => $context['occurred_at'] ?? now(),
                'created_by' => $context['created_by'] ?? null,
            ]);
        }
    }
}
