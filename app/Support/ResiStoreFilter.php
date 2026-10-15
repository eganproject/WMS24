<?php

namespace App\Support;

use App\Models\Store;
use Illuminate\Support\Collection;

/**
 * Filter toko untuk laporan berbasis resi.
 * Nilai kosong = semua toko, "none" = resi tanpa toko (import lama), angka = ID toko.
 */
class ResiStoreFilter
{
    public const NONE = 'none';

    public const NONE_LABEL = 'Tanpa Toko';

    public static function normalize(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === self::NONE) {
            return self::NONE;
        }

        return ctype_digit($value) && (int) $value > 0 ? (string) (int) $value : null;
    }

    public static function rule(): array
    {
        return ['nullable', 'string', 'regex:/^(none|[1-9][0-9]*)$/'];
    }

    public static function apply($query, string $column, ?string $storeFilter): void
    {
        if ($storeFilter === null) {
            return;
        }

        if ($storeFilter === self::NONE) {
            $query->whereNull($column);

            return;
        }

        $query->where($column, (int) $storeFilter);
    }

    public static function label(?string $storeFilter): string
    {
        if ($storeFilter === null) {
            return 'Semua toko';
        }
        if ($storeFilter === self::NONE) {
            return self::NONE_LABEL;
        }

        return (string) (Store::query()->whereKey((int) $storeFilter)->value('name') ?? '-');
    }

    public static function options(): Collection
    {
        return Store::query()->orderBy('name')->get(['id', 'name']);
    }
}
