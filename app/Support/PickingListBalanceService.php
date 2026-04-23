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
            $listRow = PickingList::query()
                ->whereDate('list_date', $date)
                ->where('sku', $sku)
                ->lockForUpdate()
                ->first();

            $listQty = (int) ($listRow?->qty ?? 0);
            $pickedQty = self::pickedQty($date, $sku);
            $remainingQty = max(0, $listQty - $pickedQty);
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

        return (int) DB::table('qc_resi_scan_items as qci')
            ->join('qc_resi_scans as qc', 'qc.id', '=', 'qci.qc_resi_scan_id')
            ->join('resis as r', 'r.id', '=', 'qc.resi_id')
            ->where('qc.status', 'passed')
            ->whereDate('r.tanggal_upload', $date)
            ->where('qci.sku', $sku)
            ->sum('qci.expected_qty');
    }

    private static function syncException(string $date, string $sku, int $exceptionQty): void
    {
        $exception = PickingListException::query()
            ->whereDate('list_date', $date)
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
}
