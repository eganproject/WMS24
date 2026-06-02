<?php

namespace App\Support;

use App\Models\Item;
use App\Models\ItemBarcode;

class ItemBarcodeResolver
{
    public function resolveItem(string $code): ?Item
    {
        $normalized = self::normalize($code);
        if ($normalized === '') {
            return null;
        }

        $item = Item::query()
            ->whereRaw('LOWER(sku) = ?', [$normalized])
            ->where('item_type', Item::TYPE_SINGLE)
            ->first();

        if ($item) {
            return $item;
        }

        return ItemBarcode::query()
            ->with('item')
            ->where('normalized_hash', self::hash($normalized))
            ->where('is_active', true)
            ->whereHas('item', fn ($q) => $q->where('item_type', Item::TYPE_SINGLE))
            ->first()
            ?->item;
    }

    public function resolveSku(string $code): string
    {
        return $this->resolveItem($code)?->sku ?? trim($code);
    }

    public static function normalize(string $code): string
    {
        return mb_strtolower(trim($code));
    }

    public static function hash(string $normalizedCode): string
    {
        return hash('sha256', $normalizedCode);
    }
}
