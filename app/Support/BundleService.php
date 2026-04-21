<?php

namespace App\Support;

use App\Models\Item;
use App\Models\ItemBundleComponent;
use App\Models\ItemStock;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class BundleService
{
    public static function assertPhysicalItems(
        iterable $itemIds,
        string $message = 'Bundle tidak bisa diproses pada transaksi stok fisik.',
        string $field = 'items'
    ): void
    {
        $ids = collect($itemIds)
            ->map(fn ($itemId) => (int) $itemId)
            ->filter(fn ($itemId) => $itemId > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return;
        }

        $bundle = Item::query()
            ->whereIn('id', $ids->all())
            ->where('item_type', Item::TYPE_BUNDLE)
            ->orderBy('sku')
            ->first(['id', 'sku']);

        if ($bundle) {
            throw ValidationException::withMessages([
                $field => $message.' SKU: '.$bundle->sku,
            ]);
        }
    }

    public static function normalizeComponents(array $rows): array
    {
        $preparedRows = [];
        $pending = [
            'component_item_id' => 0,
            'required_qty' => 0,
        ];

        foreach ($rows as $row) {
            $normalized = [
                'component_item_id' => (int) ($row['component_item_id'] ?? $row['item_id'] ?? 0),
                'required_qty' => (int) ($row['required_qty'] ?? $row['qty'] ?? 0),
            ];

            if ($normalized['component_item_id'] > 0 && $normalized['required_qty'] > 0) {
                $preparedRows[] = $normalized;
                continue;
            }

            if ($normalized['component_item_id'] > 0) {
                if ($pending['component_item_id'] > 0 && $pending['required_qty'] > 0) {
                    $preparedRows[] = $pending;
                    $pending = [
                        'component_item_id' => 0,
                        'required_qty' => 0,
                    ];
                }
                $pending['component_item_id'] = $normalized['component_item_id'];
            }

            if ($normalized['required_qty'] > 0) {
                $pending['required_qty'] = $normalized['required_qty'];
            }

            if ($pending['component_item_id'] > 0 && $pending['required_qty'] > 0) {
                $preparedRows[] = $pending;
                $pending = [
                    'component_item_id' => 0,
                    'required_qty' => 0,
                ];
            }
        }

        return collect($preparedRows)
            ->filter(fn ($row) => $row['component_item_id'] > 0 && $row['required_qty'] > 0)
            ->groupBy('component_item_id')
            ->map(function ($group, $itemId) {
                return [
                    'component_item_id' => (int) $itemId,
                    'required_qty' => (int) $group->sum('required_qty'),
                ];
            })
            ->values()
            ->all();
    }

    public static function validateComponents(?Item $bundle, array $rows): array
    {
        $normalized = self::normalizeComponents($rows);
        if (empty($normalized)) {
            throw ValidationException::withMessages([
                'bundle_components' => 'Bundle wajib memiliki minimal 1 komponen.',
            ]);
        }

        $componentIds = array_column($normalized, 'component_item_id');
        $components = Item::query()
            ->whereIn('id', $componentIds)
            ->get(['id', 'sku', 'name', 'item_type'])
            ->keyBy('id');

        foreach ($normalized as $row) {
            $component = $components->get($row['component_item_id']);
            if (!$component) {
                throw ValidationException::withMessages([
                    'bundle_components' => 'Komponen bundle tidak ditemukan.',
                ]);
            }
            if ($bundle && $component->id === $bundle->id) {
                throw ValidationException::withMessages([
                    'bundle_components' => 'Bundle tidak boleh memakai dirinya sendiri sebagai komponen.',
                ]);
            }
            if ($component->isBundle()) {
                throw ValidationException::withMessages([
                    'bundle_components' => "Komponen {$component->sku} adalah bundle. Nested bundle belum didukung.",
                ]);
            }
        }

        return $normalized;
    }

    public static function syncComponents(Item $bundle, array $rows): void
    {
        if (!$bundle->isBundle()) {
            ItemBundleComponent::where('bundle_item_id', $bundle->id)->delete();
            return;
        }

        $normalized = self::validateComponents($bundle, $rows);
        ItemBundleComponent::where('bundle_item_id', $bundle->id)->delete();

        foreach ($normalized as $row) {
            ItemBundleComponent::create([
                'bundle_item_id' => $bundle->id,
                'component_item_id' => $row['component_item_id'],
                'required_qty' => $row['required_qty'],
            ]);
        }
    }

    public static function summarize(Item $item): string
    {
        if (!$item->isBundle()) {
            return '';
        }

        $item->loadMissing('bundleComponents.component');

        return $item->bundleComponents
            ->map(function ($row) {
                $componentSku = $row->component?->sku ?? 'UNKNOWN';
                return "{$componentSku} x {$row->required_qty}";
            })
            ->implode(', ');
    }

    public static function virtualAvailableQty(Item $item, int $warehouseId): int
    {
        if (!$item->isBundle()) {
            return self::physicalAvailableQty($item->id, $warehouseId);
        }

        $item->loadMissing('bundleComponents.component');
        if ($item->bundleComponents->isEmpty()) {
            return 0;
        }

        $componentIds = $item->bundleComponents->pluck('component_item_id')->all();
        $stocks = ItemStock::query()
            ->where('warehouse_id', $warehouseId)
            ->whereIn('item_id', $componentIds)
            ->get(['item_id', 'stock'])
            ->keyBy('item_id');

        $available = null;
        foreach ($item->bundleComponents as $componentRow) {
            $requiredQty = max(1, (int) $componentRow->required_qty);
            $stock = (int) ($stocks->get($componentRow->component_item_id)?->stock ?? 0);
            $possible = intdiv(max(0, $stock), $requiredQty);
            $available = $available === null ? $possible : min($available, $possible);
        }

        return max(0, (int) ($available ?? 0));
    }

    public static function physicalAvailableQty(int $itemId, int $warehouseId): int
    {
        return (int) (ItemStock::query()
            ->where('item_id', $itemId)
            ->where('warehouse_id', $warehouseId)
            ->value('stock') ?? 0);
    }

    public static function expandSkuRows(iterable $rows): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            $sku = trim((string) ($row['sku'] ?? $row->sku ?? ''));
            $qty = (int) ($row['qty'] ?? $row->qty ?? 0);
            if ($sku === '' || $qty <= 0) {
                continue;
            }

            $item = Item::query()
                ->where('sku', $sku)
                ->with('bundleComponents.component')
                ->first();

            if (!$item) {
                throw ValidationException::withMessages([
                    'sku' => "SKU {$sku} tidak ditemukan di master item.",
                ]);
            }

            foreach (self::expandItem($item, $qty) as $requirement) {
                $reqSku = $requirement['sku'];
                if (!isset($grouped[$reqSku])) {
                    $grouped[$reqSku] = $requirement;
                    continue;
                }
                $grouped[$reqSku]['qty'] += $requirement['qty'];
            }
        }

        return array_values($grouped);
    }

    public static function expandItemRows(iterable $rows): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            $itemId = (int) ($row['item_id'] ?? $row->item_id ?? 0);
            $qty = (int) ($row['qty'] ?? $row->qty ?? 0);
            if ($itemId <= 0 || $qty <= 0) {
                continue;
            }

            $item = Item::query()
                ->with('bundleComponents.component')
                ->find($itemId);

            if (!$item) {
                throw ValidationException::withMessages([
                    'items' => 'Item outbound tidak ditemukan.',
                ]);
            }

            foreach (self::expandItem($item, $qty) as $requirement) {
                $key = (int) $requirement['item_id'];
                if (!isset($grouped[$key])) {
                    $grouped[$key] = $requirement;
                    continue;
                }
                $grouped[$key]['qty'] += $requirement['qty'];
            }
        }

        return array_values($grouped);
    }

    private static function expandItem(Item $item, int $qty): array
    {
        if (!$item->isBundle()) {
            return [[
                'item_id' => $item->id,
                'sku' => $item->sku,
                'qty' => $qty,
                'reference_item_id' => $item->id,
                'reference_sku' => $item->sku,
            ]];
        }

        $item->loadMissing('bundleComponents.component');
        if ($item->bundleComponents->isEmpty()) {
            throw ValidationException::withMessages([
                'bundle_components' => "Bundle {$item->sku} belum memiliki komponen.",
            ]);
        }

        $requirements = [];
        foreach ($item->bundleComponents as $componentRow) {
            $component = $componentRow->component;
            if (!$component || $component->isBundle()) {
                throw ValidationException::withMessages([
                    'bundle_components' => "Bundle {$item->sku} memiliki komponen yang tidak valid.",
                ]);
            }

            $requirements[] = [
                'item_id' => $component->id,
                'sku' => $component->sku,
                'qty' => $qty * (int) $componentRow->required_qty,
                'reference_item_id' => $item->id,
                'reference_sku' => $item->sku,
            ];
        }

        return $requirements;
    }
}
