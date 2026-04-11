<?php

namespace App\Support;

class QcTransitStatus
{
    public const DRAFT = 'draft';
    public const PASSED = 'passed';

    public const NEXT_IN_PROGRESS = 'in_progress';
    public const NEXT_READY_PACKING = 'ready_packing';
    public const NEXT_FORWARDED = 'forwarded';

    public static function scanStatusLabel(?string $status): string
    {
        return match ($status) {
            self::PASSED => 'Lolos QC',
            default => 'QC Berjalan',
        };
    }

    public static function scanStatusBadgeClass(?string $status): string
    {
        return match ($status) {
            self::PASSED => 'badge-light-success',
            default => 'badge-light-warning',
        };
    }

    public static function nextStageKey(?string $status, bool $packerScanned): string
    {
        if (($status ?? self::DRAFT) === self::DRAFT) {
            return self::NEXT_IN_PROGRESS;
        }

        if ($packerScanned) {
            return self::NEXT_FORWARDED;
        }

        return self::NEXT_READY_PACKING;
    }

    public static function nextStageLabel(string $key): string
    {
        return match ($key) {
            self::NEXT_FORWARDED => 'Sudah ke Packer',
            self::NEXT_READY_PACKING => 'Siap Packing',
            default => 'QC Berjalan',
        };
    }

    public static function nextStageBadgeClass(string $key): string
    {
        return match ($key) {
            self::NEXT_FORWARDED => 'badge-light-success',
            self::NEXT_READY_PACKING => 'badge-light-info',
            default => 'badge-light-warning',
        };
    }
}
