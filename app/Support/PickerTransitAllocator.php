<?php

namespace App\Support;

use App\Models\Item;
use App\Models\PackerScanException;
use App\Models\PickerTransitItem;
use App\Models\QcResiScanItem;

class PickerTransitAllocator
{
    public static function isExceptionSku(string $sku): bool
    {
        $normalized = strtolower(trim($sku));
        if ($normalized === '') {
            return false;
        }

        return PackerScanException::query()
            ->whereRaw('LOWER(sku) = ?', [$normalized])
            ->exists();
    }

    public static function splitSkuTotals(iterable $details, string $qtyField = 'expected_qty'): array
    {
        $exceptionSkus = PackerScanException::query()
            ->pluck('sku')
            ->map(fn ($sku) => strtolower(trim((string) $sku)))
            ->filter()
            ->values()
            ->all();

        $exceptionLookup = array_flip($exceptionSkus);
        $skuTotals = [];
        $excludedTotals = [];

        foreach ($details as $detail) {
            $sku = trim((string) data_get($detail, 'sku'));
            $qty = (int) data_get($detail, $qtyField, 0);

            if ($sku === '' || $qty <= 0) {
                continue;
            }

            $skuKey = strtolower($sku);
            if (isset($exceptionLookup[$skuKey])) {
                $excludedTotals[$sku] = ($excludedTotals[$sku] ?? 0) + $qty;
                continue;
            }

            $skuTotals[$sku] = ($skuTotals[$sku] ?? 0) + $qty;
        }

        return [$skuTotals, $excludedTotals];
    }

    public static function availableQtyForAdditionalScan(string $sku, string $scanDate): array
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

        $transitRow = PickerTransitItem::where('item_id', $item->id)
            ->where('picked_date', '<=', $scanDate)
            ->where('remaining_qty', '>', 0)
            ->orderByDesc('picked_date')
            ->lockForUpdate()
            ->first();

        if (!$transitRow) {
            return [
                'available' => 0,
                'reason' => 'Transit hari ini belum tersedia',
            ];
        }

        $reservedQty = (int) QcResiScanItem::query()
            ->join('qc_resi_scans', 'qc_resi_scans.id', '=', 'qc_resi_scan_items.qc_resi_scan_id')
            ->whereRaw('LOWER(qc_resi_scan_items.sku) = ?', [strtolower($normalizedSku)])
            ->whereIn('qc_resi_scans.status', [QcTransitStatus::DRAFT, QcTransitStatus::HOLD])
            ->lockForUpdate()
            ->sum('qc_resi_scan_items.scanned_qty');

        return [
            'available' => max(0, (int) $transitRow->remaining_qty - $reservedQty),
            'reason' => null,
        ];
    }

    public static function prepareAllocations(array $skuTotals, string $scanDate): array
    {
        if (empty($skuTotals)) {
            return [
                'issues' => [],
                'updates' => [],
            ];
        }

        $items = Item::whereIn('sku', array_keys($skuTotals))
            ->get(['id', 'sku', 'name'])
            ->keyBy('sku');

        $issues = [];
        $updates = [];

        foreach ($skuTotals as $sku => $qty) {
            $item = $items->get($sku);
            if (!$item) {
                $issues[] = [
                    'sku' => $sku,
                    'required' => $qty,
                    'reason' => 'SKU tidak ditemukan',
                ];
                continue;
            }

            $transitRow = PickerTransitItem::where('item_id', $item->id)
                ->where('picked_date', '<=', $scanDate)
                ->where('remaining_qty', '>', 0)
                ->orderByDesc('picked_date')
                ->lockForUpdate()
                ->first();

            if (!$transitRow) {
                $issues[] = [
                    'sku' => $sku,
                    'required' => $qty,
                    'reason' => 'Transit hari ini belum tersedia',
                ];
                continue;
            }

            $remaining = (int) $transitRow->remaining_qty;
            if ($remaining < $qty) {
                $issues[] = [
                    'sku' => $sku,
                    'required' => $qty,
                    'available' => $remaining,
                    'reason' => 'Sisa transit tidak mencukupi',
                ];
                continue;
            }

            $updates[] = [
                'row' => $transitRow,
                'qty' => $qty,
            ];
        }

        return [
            'issues' => $issues,
            'updates' => $updates,
        ];
    }

    public static function applyAllocations(array $updates): void
    {
        foreach ($updates as $update) {
            $row = $update['row'];
            $row->remaining_qty = max(0, (int) $row->remaining_qty - (int) $update['qty']);
            $row->save();
        }
    }
}
