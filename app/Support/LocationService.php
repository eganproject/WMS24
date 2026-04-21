<?php

namespace App\Support;

use App\Models\Lane;
use App\Models\Location;

class LocationService
{
    public static function normalizeLaneCode(string $laneCode): ?string
    {
        $lane = strtoupper(trim($laneCode));
        if ($lane === '') {
            return null;
        }

        if (!preg_match('/^[A-Z0-9]+$/', $lane)) {
            return null;
        }

        return $lane;
    }

    public static function resolveLane(string $laneCode): ?Lane
    {
        $normalized = self::normalizeLaneCode($laneCode);
        if ($normalized === null) {
            return null;
        }

        $lane = Lane::query()
            ->whereRaw('UPPER(code) = ?', [$normalized])
            ->first();

        if ($lane) {
            if ($lane->code !== $normalized) {
                $lane->code = $normalized;
                $lane->save();
            }

            return $lane;
        }

        return Lane::create([
            'code' => $normalized,
            'name' => $normalized,
            'is_active' => true,
        ]);
    }

    /**
     * Parse address code in format: LANE-RACK-COLUMN-ROW (e.g. KAB-A-3-5).
     *
     * @return array{lane_code:string,rack_code:string,column_no:int,row_no:int,code:string}|null
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

        [$laneRaw, $rackRaw, $colRaw, $rowRaw] = $parts;
        $lane = self::normalizeLaneCode($laneRaw);
        $rack = strtoupper(trim($rackRaw));

        if ($lane === null || $rack === '') {
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
        $code = "{$lane}-{$rack}-{$colLabel}-{$rowLabel}";

        return [
            'lane_code' => $lane,
            'rack_code' => $rack,
            'column_no' => $col,
            'row_no' => $row,
            'code' => $code,
        ];
    }

    public static function buildAddress(string $laneCode, string $rackCode, int $columnNo, int $rowNo): string
    {
        $lane = strtoupper(trim($laneCode));
        $rack = strtoupper(trim($rackCode));
        $colLabel = str_pad((string) $columnNo, 2, '0', STR_PAD_LEFT);
        $rowLabel = str_pad((string) $rowNo, 2, '0', STR_PAD_LEFT);

        return "{$lane}-{$rack}-{$colLabel}-{$rowLabel}";
    }

    public static function resolveLocation(string $address): ?Location
    {
        $parsed = self::parseAddress($address);
        if (!$parsed) {
            return null;
        }

        $lane = self::resolveLane($parsed['lane_code']);
        if (!$lane) {
            return null;
        }

        $location = Location::firstOrCreate(
            [
                'lane_id' => $lane->id,
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

    public static function resolveLocationFromParts(int $laneId, string $rackCode, int $columnNo, int $rowNo): ?Location
    {
        $lane = Lane::find($laneId);
        if (!$lane) {
            return null;
        }

        $rack = strtoupper(trim($rackCode));
        $columnNo = max(1, (int) $columnNo);
        $rowNo = max(1, (int) $rowNo);
        $code = self::buildAddress($lane->code, $rack, $columnNo, $rowNo);

        $location = Location::firstOrCreate(
            [
                'lane_id' => $lane->id,
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
