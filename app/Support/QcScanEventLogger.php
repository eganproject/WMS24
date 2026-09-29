<?php

namespace App\Support;

use App\Models\QcResiScan;
use App\Models\QcResiScanEvent;

class QcScanEventLogger
{
    public function record(QcResiScan $qc, string $type, array $attributes = []): QcResiScanEvent
    {
        return QcResiScanEvent::create(array_merge([
            'qc_resi_scan_id' => $qc->id,
            'resi_id' => $qc->resi_id,
            'picker_employee_id' => $qc->picker_employee_id,
            'event_type' => $type,
            'created_by' => auth()->id(),
            'occurred_at' => now(),
        ], $attributes));
    }
}
