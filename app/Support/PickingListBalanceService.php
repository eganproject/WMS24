<?php

namespace App\Support;

use App\Models\Item;
use App\Models\PickingList;
use App\Models\PickingListException;
use Illuminate\Support\Facades\DB;

class PickingListBalanceService
{
    public static function syncForDateSkus(string $date, iterable $skus): void
    {
        $uniqueSkus = collect($skus)
            ->map(fn ($sku) => trim((string) $sku))
            ->filter()
            ->unique()
            ->values();

        foreach ($uniqueSkus as $sku) {
            $listRow = SameDay::apply(PickingList::query(), 'list_date', $date)
                ->where('sku', $sku)
                ->lockForUpdate()
                ->first();

            $listQty = (int) ($listRow?->qty ?? 0);
            $pickedQty = self::pickedQty($date, $sku);
            $substitutedOutQty = self::substitutedOutQty($date, $sku);
            $effectivePickedQty = $pickedQty + $substitutedOutQty;
            $remainingQty = max(0, $listQty - $effectivePickedQty);
            $exceptionQty = max(0, $pickedQty - $listQty);

            if ($listRow) {
                $listRow->remaining_qty = $remainingQty;
                if ((int) $listRow->qty <= 0 && $remainingQty <= 0) {
                    $listRow->delete();
                } else {
                    $listRow->save();
                }
            }

            self::syncException($date, $sku, $exceptionQty);
        }
    }

    private static function pickedQty(string $date, string $sku): int
    {
        $item = Item::query()
            ->where('sku', $sku)
            ->first(['id', 'item_type']);

        if (!$item || $item->isBundle()) {
            return 0;
        }

        $query = DB::table('qc_resi_scan_items as qci')
            ->join('qc_resi_scans as qc', 'qc.id', '=', 'qci.qc_resi_scan_id')
            ->join('resis as r', 'r.id', '=', 'qc.resi_id')
            ->where('qc.status', 'passed');

        return (int) SameDay::apply($query, 'r.tanggal_upload', $date)
            ->where('qci.sku', $sku)
            ->sum('qci.expected_qty');
    }

    private static function syncException(string $date, string $sku, int $exceptionQty): void
    {
        $exception = SameDay::apply(PickingListException::query(), 'list_date', $date)
            ->where('sku', $sku)
            ->lockForUpdate()
            ->first();

        if ($exceptionQty > 0) {
            if ($exception) {
                $exception->qty = $exceptionQty;
                $exception->save();

                return;
            }

            PickingListException::create([
                'list_date' => $date,
                'sku' => $sku,
                'qty' => $exceptionQty,
            ]);

            return;
        }

        if ($exception) {
            $exception->delete();
        }
    }

    private static function substitutedOutQty(string $date, string $sku): int
    {
        $query = DB::table('qc_resi_scan_substitutions as sub')
            ->join('qc_resi_scans as qc', 'qc.id', '=', 'sub.qc_resi_scan_id')
            ->join('resis as r', 'r.id', '=', 'qc.resi_id')
            ->where('qc.status', 'passed');

        return (int) SameDay::apply($query, 'r.tanggal_upload', $date)
            ->where('sub.original_sku', $sku)
            ->sum('sub.qty');
    }
}
