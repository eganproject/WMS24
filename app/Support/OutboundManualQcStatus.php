<?php

namespace App\Support;

class OutboundManualQcStatus
{
    public const PENDING = 'pending';
    public const PENDING_QC = 'pending_qc';
    public const QC_SCANNING = 'qc_scanning';
    public const APPROVED = 'approved';

    public static function labels(): array
    {
        return [
            self::PENDING => 'Menunggu Approval',
            self::PENDING_QC => 'Menunggu QC',
            self::QC_SCANNING => 'Sedang QC',
            self::APPROVED => 'Selesai',
        ];
    }

    public static function lockedForEdit(): array
    {
        return [
            self::PENDING_QC,
            self::QC_SCANNING,
            self::APPROVED,
        ];
    }
}
