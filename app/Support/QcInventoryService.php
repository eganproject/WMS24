<?php

namespace App\Support;

use App\Models\Item;
use App\Models\ItemStock;
use App\Models\QcResiScan;
use App\Models\QcResiScanItem;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class QcInventoryService
{
    public static function availableQtyForAdditionalScan(string $sku, ?int $excludeQcId = null): array
    {
        $normalizedSku = trim($sku);
        if ($normalizedSku === '') {
            return [
                'available' => 0,
                'reason' => 'SKU tidak valid',
            ];
        }

        if (self::isExceptionSku($normalizedSku)) {
            return [
                'available' => PHP_INT_MAX,
                'reason' => null,
            ];
        }

        $item = Item::query()
            ->where('sku', $normalizedSku)
            ->where('item_type', Item::TYPE_SINGLE)
            ->first(['id', 'sku']);
        if (!$item) {
            return [
                'available' => 0,
                'reason' => 'SKU tidak ditemukan atau bukan item stok fisik',
            ];
        }

        $displayWarehouseId = WarehouseService::displayWarehouseId();
        $stockRow = ItemStock::query()
            ->where('item_id', $item->id)
            ->where('warehouse_id', $displayWarehouseId)
            ->lockForUpdate()
            ->first();

        $currentStock = (int) ($stockRow?->stock ?? 0);
        $reservedQty = self::reservedQtyForOpenQc((string) $item->sku, $excludeQcId);

        return [
            'available' => max(0, $currentStock - $reservedQty),
            'reason' => null,
        ];
    }

    public static function assertAvailabilityForCompletion(QcResiScan $qc, Collection $items): void
    {
        foreach ($items as $row) {
            $sku = trim((string) $row->sku);
            $qty = (int) $row->scanned_qty;
            if ($sku === '' || $qty <= 0 || self::isExceptionSku($sku)) {
                continue;
            }

            $availability = self::availableQtyForAdditionalScan($sku, $qc->id);
            if (($availability['available'] ?? 0) < $qty) {
                throw ValidationException::withMessages([
                    'qty' => sprintf('Stok display untuk SKU %s tidak mencukupi.', $sku),
                ]);
            }
        }
    }

    public static function itemMapForQcItems(Collection $items): Collection
    {
        return Item::query()
            ->whereIn('sku', $items->pluck('sku')->filter()->values()->all())
            ->where('item_type', Item::TYPE_SINGLE)
            ->get(['id', 'sku', 'name'])
            ->keyBy('sku');
    }

    public static function reservedQtyForOpenQc(string $sku, ?int $excludeQcId = null): int
    {
        // Callers lock the item_stocks row of this SKU first, which already serializes
        // every reservation of the SKU. So only the items of OPEN QCs are locked here,
        // by exact (qc_resi_scan_id, sku) key. Locking through the sku index or a join
        // would also lock finished QCs and block other operators' QC and scan out.
        $openQcIds = QcResiScan::query()
            ->whereIn('status', [QcTransitStatus::DRAFT, QcTransitStatus::HOLD])
            ->when($excludeQcId && $excludeQcId > 0, fn ($q) => $q->where('id', '!=', $excludeQcId))
            ->pluck('id')
            ->all();
        if (empty($openQcIds)) {
            return 0;
        }

        $total = 0;
        foreach (array_chunk($openQcIds, 500) as $chunk) {
            $total += (int) QcResiScanItem::query()
                ->whereIn('qc_resi_scan_id', $chunk)
                ->where('sku', trim($sku))
                ->lockForUpdate()
                ->sum('scanned_qty');
        }

        return $total;
    }

    private static function isExceptionSku(string $sku): bool
    {
        return QcScanExceptionRegistry::contains($sku);
    }
}
