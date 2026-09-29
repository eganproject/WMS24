<?php

namespace App\Support;

class QcReasonCategory
{
    public const HOLD = 'hold';
    public const RESET = 'reset';
    public const SUBSTITUTION = 'substitution';

    public const OTHER = 'other';

    // Kategori yang menunjukkan kesalahan di proses picking (dipakai untuk laporan akurasi picker).
    public const PICKER_FAULT_CODES = ['short_pick', 'wrong_item_picked'];

    public static function options(string $action): array
    {
        return match ($action) {
            self::HOLD => [
                'short_pick' => 'Barang kurang diambil picker',
                'wrong_item_picked' => 'Picker salah ambil barang',
                'stock_not_found' => 'Stok tidak ditemukan di rak',
                'damaged_item' => 'Barang rusak / cacat',
                'barcode_issue' => 'Barcode rusak / tidak terbaca',
                self::OTHER => 'Lainnya',
            ],
            self::RESET => [
                'wrong_item_picked' => 'Isi resi tertukar / picker salah ambil',
                'qc_scan_error' => 'Salah scan oleh operator QC',
                'wrong_resi' => 'Salah resi yang discan',
                'barcode_issue' => 'Barcode rusak / tidak terbaca',
                self::OTHER => 'Lainnya',
            ],
            self::SUBSTITUTION => [
                'buyer_request' => 'Permintaan pembeli',
                'stock_out' => 'Stok SKU asal habis',
                'damaged_item' => 'SKU asal rusak / cacat',
                'admin_decision' => 'Keputusan admin / CS',
                self::OTHER => 'Lainnya',
            ],
            default => [],
        };
    }

    public static function all(): array
    {
        return [
            self::HOLD => self::options(self::HOLD),
            self::RESET => self::options(self::RESET),
            self::SUBSTITUTION => self::options(self::SUBSTITUTION),
        ];
    }

    public static function codes(string $action): array
    {
        return array_keys(self::options($action));
    }

    public static function label(string $action, ?string $code): ?string
    {
        return $code === null ? null : (self::options($action)[$code] ?? null);
    }

    public static function isPickerFault(?string $code): bool
    {
        return in_array($code, self::PICKER_FAULT_CODES, true);
    }

    public static function compose(string $action, string $code, ?string $note): string
    {
        $label = self::label($action, $code) ?? $code;
        $note = trim((string) $note);

        return mb_substr($note === '' ? $label : "{$label} - {$note}", 0, 500);
    }
}
