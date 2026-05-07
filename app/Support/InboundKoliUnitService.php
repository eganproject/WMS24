<?php

namespace App\Support;

use App\Models\InboundItem;
use App\Models\InboundKoliUnit;
use App\Models\InboundTransaction;
use App\Support\InboundScanStatus;
use Illuminate\Support\Collection;

class InboundKoliUnitService
{
    private const QR_PREFIX = 'INB';
    private const QR_SEPARATOR = '~';

    public function syncForTransaction(InboundTransaction $transaction): Collection
    {
        $transaction->loadMissing(['items.item', 'scanSession.items']);
        $units = collect();

        foreach ($transaction->items as $row) {
            $units = $units->merge($this->syncForInboundItem($transaction, $row));
        }

        return $units->values();
    }

    public function syncForInboundItem(InboundTransaction $transaction, InboundItem $row): Collection
    {
        $item = $row->item;
        if (!$item) {
            return collect();
        }

        $qty = (int) ($row->qty ?? 0);
        $koli = (int) ($row->koli ?? 0);
        if ($qty <= 0 || $koli <= 0 || $qty % $koli !== 0) {
            return collect();
        }

        $qtyPerKoli = (int) ($qty / $koli);
        $units = collect();
        $receivedKoli = $this->receivedKoliFor($transaction, $row);

        for ($no = 1; $no <= $koli; $no++) {
            $unit = InboundKoliUnit::firstOrNew([
                'inbound_item_id' => $row->id,
                'koli_no' => $no,
            ]);

            $unit->fill([
                'code' => $this->codeFor($transaction, $row, $no),
                'inbound_transaction_id' => $transaction->id,
                'item_id' => $row->item_id,
                'sku' => (string) $item->sku,
                'qty_per_koli' => $qtyPerKoli,
                'qty' => $qtyPerKoli,
            ]);

            if (!$unit->exists || in_array((string) $unit->status, [InboundKoliUnit::STATUS_AVAILABLE, InboundKoliUnit::STATUS_NOT_RECEIVED, ''], true)) {
                $unit->status = $receivedKoli === null || $no <= $receivedKoli
                    ? InboundKoliUnit::STATUS_AVAILABLE
                    : InboundKoliUnit::STATUS_NOT_RECEIVED;
            }

            $unit->save();
            $units->push($unit);
        }

        return $units;
    }

    public function codeFor(InboundTransaction $transaction, InboundItem $row, int $koliNo): string
    {
        $inboundCode = str_replace(self::QR_SEPARATOR, '-', trim((string) $transaction->code));
        $sku = str_replace(self::QR_SEPARATOR, '-', trim((string) ($row->item?->sku ?? '')));

        return implode(self::QR_SEPARATOR, [
            self::QR_PREFIX,
            $inboundCode,
            $sku,
            $koliNo,
        ]);
    }

    private function receivedKoliFor(InboundTransaction $transaction, InboundItem $row): ?int
    {
        if (($transaction->status ?? '') !== InboundScanStatus::COMPLETED || !$transaction->scanSession) {
            return null;
        }

        $scanItem = $transaction->scanSession->items
            ->first(fn ($scanRow) => (int) $scanRow->item_id === (int) $row->item_id);

        return $scanItem ? (int) $scanItem->scanned_koli : null;
    }
}
