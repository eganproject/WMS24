<?php

namespace App\Support;

use App\Models\Item;
use Illuminate\Validation\ValidationException;

class InboundScanExpectation
{
    /**
     * @return array{qty:int,koli:int,qty_per_koli:int}
     */
    public static function resolve(Item $item, int $qty, ?int $koli = null): array
    {
        $qty = (int) $qty;
        $koli = $koli !== null && $koli > 0 ? (int) $koli : null;
        $qtyPerKoli = (int) ($item->koli_qty ?? 0);
        $sku = trim((string) ($item->sku ?? 'item'));

        if ($qty <= 0) {
            throw ValidationException::withMessages([
                'items' => "Qty inbound tidak valid untuk SKU {$sku}.",
            ]);
        }

        if ($qtyPerKoli <= 0) {
            throw ValidationException::withMessages([
                'items' => "SKU {$sku} belum punya isi per koli. Lengkapi koli_qty di master item terlebih dahulu.",
            ]);
        }

        if ($koli !== null) {
            $expectedQty = $koli * $qtyPerKoli;
            if ($expectedQty !== $qty) {
                throw ValidationException::withMessages([
                    'items' => "Qty SKU {$sku} harus sama dengan koli x isi/koli ({$koli} x {$qtyPerKoli} = {$expectedQty}).",
                ]);
            }

            return [
                'qty' => $qty,
                'koli' => $koli,
                'qty_per_koli' => $qtyPerKoli,
            ];
        }

        if ($qty % $qtyPerKoli !== 0) {
            throw ValidationException::withMessages([
                'items' => "Qty SKU {$sku} tidak bisa dibagi rata per koli. Isi/koli {$qtyPerKoli}, qty inbound {$qty}.",
            ]);
        }

        return [
            'qty' => $qty,
            'koli' => (int) ($qty / $qtyPerKoli),
            'qty_per_koli' => $qtyPerKoli,
        ];
    }
}
