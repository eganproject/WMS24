<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class AttendanceReportExport implements WithMultipleSheets
{
    public function __construct(
        private Collection $rows,
        private array $summary,
        private array $period
    ) {
    }

    public function sheets(): array
    {
        return [
            new AttendanceReportSummarySheet($this->rows, $this->summary, $this->period),
            new AttendanceReportDetailSheet($this->rows, $this->period),
        ];
    }
}
