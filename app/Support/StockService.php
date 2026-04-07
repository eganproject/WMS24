<?php

namespace App\Support;

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
}
