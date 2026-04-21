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

    public static function resolveArea(string $areaCode): ?Area
    {
        $normalized = self::normalizeAreaCode($areaCode);
        if ($normalized === null) {
            return null;
        }

        $area = Area::query()
            ->whereRaw('UPPER(code) = ?', [$normalized])
            ->first();

        if ($area) {
            if ($area->code !== $normalized) {
                $area->code = $normalized;
                $area->save();
            }

            return $area;
        }

        return Area::create([
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

    public static function resolveLocation(string $address): ?Location
    {
        $parsed = self::parseAddress($address);
        if (!$parsed) {
            return null;
        }

        $area = self::resolveArea($parsed['area_code']);
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
