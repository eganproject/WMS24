<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Nomor surat jalan outbound manual yang ringkas dan berurutan per hari.
 *
 * Format: SJM-YYMMDD-NNNN, contoh SJM-261103-0001.
 * Nomor lama (SJ-OUT-MNL-YYYYMMDDHHMMSS-XXXX) tidak disentuh dan tidak
 * mungkin bentrok karena awalannya berbeda.
 */
class DeliveryNoteNumber
{
    public const MANUAL_PREFIX = 'SJM';

    public static function nextManual(?Carbon $at = null): string
    {
        $at = ($at ?? now())->copy()->timezone(config('app.timezone'));
        $prefix = self::MANUAL_PREFIX.'-'.$at->format('ymd');

        return DB::transaction(function () use ($prefix) {
            $key = 'delivery_note:'.$prefix;
            $now = now();

            DB::table('document_sequences')->insertOrIgnore([
                'sequence_key' => $key,
                'last_number' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Kunci baris counter supaya dua transaksi bersamaan tidak dapat nomor yang sama.
            $number = (int) DB::table('document_sequences')
                ->where('sequence_key', $key)
                ->lockForUpdate()
                ->value('last_number');

            // Lewati nomor yang kebetulan sudah dipakai (mis. diketik manual oleh user).
            do {
                $number++;
                $candidate = $prefix.'-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
            } while (DB::table('outbound_transactions')->where('surat_jalan_no', $candidate)->exists());

            DB::table('document_sequences')
                ->where('sequence_key', $key)
                ->update(['last_number' => $number, 'updated_at' => $now]);

            return $candidate;
        });
    }
}
