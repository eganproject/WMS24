<?php

namespace App\Support;

class InboundScanStatus
{
    public const PENDING_SCAN = 'pending_scan';
    public const SCANNING = 'scanning';
    public const COMPLETED = 'completed';

    public static function all(): array
    {
        return [
            self::PENDING_SCAN,
            self::SCANNING,
            self::COMPLETED,
        ];
    }

    public static function label(?string $status): string
    {
        return match ($status) {
            self::SCANNING => 'Sedang Scan',
            self::COMPLETED => 'Selesai',
            default => 'Menunggu Scan',
        };
    }
}
