<?php

namespace App\Support;

use App\Models\ItemBarcodeScanMiss;

class ItemBarcodeScanMissLogger
{
    public const CONTEXT_QC_SCAN = 'qc_scan_sku';
    public const CONTEXT_STOCK_OPNAME = 'stock_opname';

    public function log(string $context, string $code, ?int $sourceId = null, ?string $sourceCode = null): void
    {
        $code = trim($code);
        $normalized = ItemBarcodeResolver::normalize($code);
        if ($normalized === '') {
            return;
        }

        $row = ItemBarcodeScanMiss::query()
            ->whereNull('resolved_at')
            ->where('context', $context)
            ->where('normalized_hash', ItemBarcodeResolver::hash($normalized))
            ->when($sourceId === null, fn ($q) => $q->whereNull('source_id'), fn ($q) => $q->where('source_id', $sourceId))
            ->when($sourceCode === null || $sourceCode === '', fn ($q) => $q->whereNull('source_code'), fn ($q) => $q->where('source_code', $sourceCode))
            ->first();

        if ($row) {
            $row->scan_count = (int) $row->scan_count + 1;
            $row->last_scanned_at = now();
            $row->save();
            return;
        }

        ItemBarcodeScanMiss::create([
            'context' => $context,
            'source_id' => $sourceId,
            'source_code' => $sourceCode !== '' ? $sourceCode : null,
            'scan_code' => $code,
            'normalized_code' => $normalized,
            'normalized_hash' => ItemBarcodeResolver::hash($normalized),
            'scan_count' => 1,
            'last_scanned_at' => now(),
            'created_by' => auth()->id(),
        ]);
    }
}
