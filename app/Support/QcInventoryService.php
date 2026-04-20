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

        $item = Item::where('sku', $normalizedSku)->first(['id', 'sku']);
        if (!$item) {
            return [
                'available' => 0,
                'reason' => 'SKU tidak ditemukan',
            ];
        }

        $displayWarehouseId = WarehouseService::displayWarehouseId();
        $stockRow = ItemStock::query()
            ->where('item_id', $item->id)
            ->where('warehouse_id', $displayWarehouseId)
            ->lockForUpdate()
            ->first();

        $currentStock = (int) ($stockRow?->stock ?? 0);
        $reservedQty = self::reservedQtyForOpenQc($normalizedSku, $excludeQcId);

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
            ->get(['id', 'sku', 'name'])
            ->keyBy('sku');
    }

    public static function reservedQtyForOpenQc(string $sku, ?int $excludeQcId = null): int
    {
        $query = QcResiScanItem::query()
            ->join('qc_resi_scans', 'qc_resi_scans.id', '=', 'qc_resi_scan_items.qc_resi_scan_id')
            ->whereRaw('LOWER(qc_resi_scan_items.sku) = ?', [strtolower(trim($sku))])
            ->whereIn('qc_resi_scans.status', [QcTransitStatus::DRAFT, QcTransitStatus::HOLD]);

        if ($excludeQcId && $excludeQcId > 0) {
            $query->where('qc_resi_scans.id', '!=', $excludeQcId);
        }

        return (int) $query->lockForUpdate()->sum('qc_resi_scan_items.scanned_qty');
    }

    private static function isExceptionSku(string $sku): bool
    {
        return QcScanExceptionRegistry::contains($sku);
    }
}
