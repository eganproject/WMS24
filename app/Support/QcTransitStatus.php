<?php

namespace App\Support;

class QcTransitStatus
{
    public const DRAFT = 'draft';
    public const HOLD = 'hold';
    public const PASSED = 'passed';

    public const NEXT_IN_PROGRESS = 'in_progress';
    public const NEXT_ON_HOLD = 'on_hold';
    public const NEXT_READY_SCAN_OUT = 'ready_scan_out';

    public static function scanStatusLabel(?string $status): string
    {
        return match ($status) {
            self::PASSED => 'Lolos QC',
            self::HOLD => 'QC Ditunda',
            default => 'QC Berjalan',
        };
    }

    public static function scanStatusBadgeClass(?string $status): string
    {
        return match ($status) {
            self::PASSED => 'badge-light-success',
            self::HOLD => 'badge-light-danger',
            default => 'badge-light-warning',
        };
    }

    public static function nextStageKey(?string $status): string
    {
        if (($status ?? self::DRAFT) === self::HOLD) {
            return self::NEXT_ON_HOLD;
        }

        if (($status ?? self::DRAFT) === self::DRAFT) {
            return self::NEXT_IN_PROGRESS;
        }

        return self::NEXT_READY_SCAN_OUT;
    }

    public static function nextStageLabel(string $key): string
    {
        return match ($key) {
            self::NEXT_ON_HOLD => 'Menunggu Transit',
            self::NEXT_READY_SCAN_OUT => 'Siap Scan Out',
            default => 'QC Berjalan',
        };
    }

    public static function nextStageBadgeClass(string $key): string
    {
        return match ($key) {
            self::NEXT_ON_HOLD => 'badge-light-danger',
            self::NEXT_READY_SCAN_OUT => 'badge-light-info',
            default => 'badge-light-warning',
        };
    }
}
