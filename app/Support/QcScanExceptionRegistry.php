<?php

namespace App\Support;

use App\Models\QcScanException;

class QcScanExceptionRegistry
{
    public static function contains(string $sku): bool
    {
        $sku = strtoupper(trim($sku));
        if ($sku === '') {
            return false;
        }

        return QcScanException::query()
            ->whereRaw('UPPER(sku) = ?', [$sku])
            ->exists();
    }
}
