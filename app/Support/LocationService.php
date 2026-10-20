<?php

namespace App\Support;

use App\Models\Area;
use App\Models\Location;

class LocationService
{
    public static function normalizeAreaCode(string $areaCode): ?string
    {
        $area = strtoupper(trim($areaCode));
        if ($area === '') {
            return null;
        }

        if (!preg_match('/^[A-Z0-9]+$/', $area)) {
            return null;
        }

        return $area;
    }

    /**
     * Area tanpa warehouse_id (data lama) dianggap milik gudang display.
     */
    public static function areaWarehouseId(Area $area): int
    {
        return $area->warehouse_id ? (int) $area->warehouse_id : WarehouseService::displayWarehouseId();
    }

    public static function areaBelongsToWarehouse(Area $area, int $warehouseId): bool
    {
        return self::areaWarehouseId($area) === $warehouseId;
    }

    public static function findAreaByCode(string $areaCode): ?Area
    {
        $normalized = self::normalizeAreaCode($areaCode);
        if ($normalized === null) {
            return null;
        }

        return Area::query()->whereRaw('UPPER(code) = ?', [$normalized])->first();
    }

    /**
     * True jika area pada alamat (kode area saja atau AREA-RACK-KOLOM-BARIS) belum ada
     * atau memang milik gudang $warehouseId. Tidak membuat data baru.
     */
    public static function addressFitsWarehouse(string $address, int $warehouseId): bool
    {
        $address = trim($address);
        if ($address === '') {
            return true;
        }

        $areaCode = self::parseAddress($address)['area_code'] ?? $address;
        $area = self::findAreaByCode($areaCode);

        return !$area || self::areaBelongsToWarehouse($area, $warehouseId);
    }

    /**
     * Resolve bin location gudang besar dari alamat lengkap AREA-RACK-KOLOM-BARIS.
     *
     * @throws \InvalidArgumentException jika format salah atau area milik gudang lain.
     */
    public static function resolveMainWarehouseLocation(string $address): ?Location
    {
        return self::resolveSlotLocation($address, WarehouseService::defaultWarehouseId(), 'lokasi gudang besar');
    }

    /**
     * Resolve slot lengkap AREA-RACK-KOLOM-BARIS yang wajib milik gudang $warehouseId.
     *
     * @throws \InvalidArgumentException jika format salah atau area milik gudang lain.
     */
    public static function resolveSlotLocation(string $address, int $warehouseId, string $label): ?Location
    {
        $address = trim($address);
        if ($address === '') {
            return null;
        }

        if (!self::parseAddress($address)) {
            throw new \InvalidArgumentException("format {$label} harus AREA-RACK-KOLOM-BARIS.");
        }

        $location = self::resolveLocation($address, $warehouseId);
        if (!$location) {
            $warehouseLabel = $warehouseId === WarehouseService::defaultWarehouseId() ? 'gudang besar' : 'gudang display';
            throw new \InvalidArgumentException("area pada {$label} bukan area {$warehouseLabel}.");
        }

        return $location;
    }

    /**
     * Cari (atau buat) area berdasarkan kode. Jika $warehouseId diisi, area yang sudah ada
     * harus milik gudang tersebut; jika milik gudang lain hasilnya null.
     */
    public static function resolveArea(string $areaCode, ?int $warehouseId = null): ?Area
    {
        $normalized = self::normalizeAreaCode($areaCode);
        if ($normalized === null) {
            return null;
        }

        $area = Area::query()
            ->whereRaw('UPPER(code) = ?', [$normalized])
            ->first();

        if ($area) {
            if ($warehouseId !== null && !self::areaBelongsToWarehouse($area, $warehouseId)) {
                return null;
            }

            if ($area->code !== $normalized) {
                $area->code = $normalized;
                $area->save();
            }

            return $area;
        }

        return Area::create([
            'warehouse_id' => $warehouseId ?? WarehouseService::displayWarehouseId(),
            'code' => $normalized,
            'name' => $normalized,
            'is_active' => true,
        ]);
    }

    /**
     * Parse address code in format: AREA-RACK-COLUMN-ROW (e.g. KAB-A-3-5).
     *
     * @return array{area_code:string,rack_code:string,column_no:int,row_no:int,code:string}|null
     */
    public static function parseAddress(string $address): ?array
    {
        $raw = trim($address);
        if ($raw === '') {
            return null;
        }

        $parts = array_values(array_filter(explode('-', $raw), fn ($part) => $part !== ''));
        if (count($parts) !== 4) {
            return null;
        }

        [$areaRaw, $rackRaw, $colRaw, $rowRaw] = $parts;
        $area = self::normalizeAreaCode($areaRaw);
        $rack = strtoupper(trim($rackRaw));

        if ($area === null || $rack === '') {
            return null;
        }
        if (!preg_match('/^[A-Z0-9]+$/', $rack)) {
            return null;
        }

        if (!ctype_digit($colRaw) || !ctype_digit($rowRaw)) {
            return null;
        }

        $col = (int) $colRaw;
        $row = (int) $rowRaw;
        if ($col < 1 || $row < 1) {
            return null;
        }

        $colLabel = str_pad((string) $col, 2, '0', STR_PAD_LEFT);
        $rowLabel = str_pad((string) $row, 2, '0', STR_PAD_LEFT);
        $code = "{$area}-{$rack}-{$colLabel}-{$rowLabel}";

        return [
            'area_code' => $area,
            'rack_code' => $rack,
            'column_no' => $col,
            'row_no' => $row,
            'code' => $code,
        ];
    }

    public static function buildAddress(string $areaCode, string $rackCode, int $columnNo, int $rowNo): string
    {
        $area = strtoupper(trim($areaCode));
        $rack = strtoupper(trim($rackCode));
        $colLabel = str_pad((string) $columnNo, 2, '0', STR_PAD_LEFT);
        $rowLabel = str_pad((string) $rowNo, 2, '0', STR_PAD_LEFT);

        return "{$area}-{$rack}-{$colLabel}-{$rowLabel}";
    }

    public static function resolveLocation(string $address, ?int $warehouseId = null): ?Location
    {
        $parsed = self::parseAddress($address);
        if (!$parsed) {
            return null;
        }

        $area = self::resolveArea($parsed['area_code'], $warehouseId);
        if (!$area) {
            return null;
        }

        $location = Location::firstOrCreate(
            [
                'area_id' => $area->id,
                'rack_code' => $parsed['rack_code'],
                'column_no' => $parsed['column_no'],
                'row_no' => $parsed['row_no'],
            ],
            ['code' => $parsed['code']]
        );

        if ($location->code !== $parsed['code']) {
            $location->code = $parsed['code'];
            $location->save();
        }

        return $location;
    }

    public static function resolveLocationFromParts(int $areaId, string $rackCode, int $columnNo, int $rowNo): ?Location
    {
        $area = Area::find($areaId);
        if (!$area) {
            return null;
        }

        $rack = strtoupper(trim($rackCode));
        $columnNo = max(1, (int) $columnNo);
        $rowNo = max(1, (int) $rowNo);
        $code = self::buildAddress($area->code, $rack, $columnNo, $rowNo);

        $location = Location::firstOrCreate(
            [
                'area_id' => $area->id,
                'rack_code' => $rack,
                'column_no' => $columnNo,
                'row_no' => $rowNo,
            ],
            ['code' => $code]
        );

        if ($location->code !== $code) {
            $location->code = $code;
            $location->save();
        }

        return $location;
    }
}
