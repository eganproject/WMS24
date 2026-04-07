<?php

namespace App\Support;

use App\Models\Warehouse;

class WarehouseService
{
    /** @var array<string,int|null> */
    private static array $codeCache = [];

    public static function defaultWarehouseCode(): string
    {
        return (string) config('inventory.default_warehouse_code', 'GUDANG_BESAR');
    }

    public static function displayWarehouseCode(): string
    {
        return (string) config('inventory.display_warehouse_code', 'GUDANG_DISPLAY');
    }

    public static function warehouseIdByCode(string $code): ?int
    {
        if (array_key_exists($code, self::$codeCache)) {
            return self::$codeCache[$code];
        }
        $id = Warehouse::where('code', $code)->value('id');
        self::$codeCache[$code] = $id ? (int) $id : null;
        return self::$codeCache[$code];
    }

    public static function defaultWarehouseId(): int
    {
        return self::warehouseIdByCode(self::defaultWarehouseCode()) ?? 1;
    }

    public static function displayWarehouseId(): int
    {
        return self::warehouseIdByCode(self::displayWarehouseCode()) ?? 2;
    }
}
