<?php

namespace App\Support;

use InvalidArgumentException;

class ItemUpdateFields
{
    private const DEFINITIONS = [
        'name' => ['label' => 'Nama Item', 'headers' => ['name'], 'description' => 'Nama item baru. Nilai tidak boleh kosong.'],
        'status' => ['label' => 'Status', 'headers' => ['status'], 'description' => 'Gunakan active atau inactive.'],
        'category' => ['label' => 'Kategori', 'headers' => ['parent_category', 'category'], 'description' => 'Nama parent dan kategori yang sudah tersedia.'],
        'address' => ['label' => 'Alamat', 'headers' => ['address'], 'description' => 'Kode area atau alamat lengkap, misalnya KAB-A-03-05.'],
        'description' => ['label' => 'Deskripsi', 'headers' => ['description'], 'description' => 'Deskripsi bebas untuk item.'],
        'safety_stock' => ['label' => 'Stok Pengaman', 'headers' => ['safety_stock'], 'description' => 'Bilangan bulat minimal 0.'],
    ];

    public static function definitions(): array
    {
        return self::DEFINITIONS;
    }

    public static function keys(): array
    {
        return array_keys(self::DEFINITIONS);
    }

    public static function normalize(array $fields): array
    {
        $requested = array_values(array_unique(array_map(fn ($field) => trim((string) $field), $fields)));
        $invalid = array_diff($requested, self::keys());
        if ($invalid !== []) {
            throw new InvalidArgumentException('Field update item tidak valid: '.implode(', ', $invalid).'.');
        }

        return array_values(array_filter(self::keys(), fn (string $field) => in_array($field, $requested, true)));
    }

    public static function headings(array $fields): array
    {
        $headings = ['sku'];
        foreach (self::normalize($fields) as $field) {
            $headings = array_merge($headings, self::DEFINITIONS[$field]['headers']);
        }

        return $headings;
    }

    public static function labels(array $fields): array
    {
        return array_map(fn (string $field) => self::DEFINITIONS[$field]['label'], self::normalize($fields));
    }
}
