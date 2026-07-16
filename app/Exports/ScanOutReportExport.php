<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ScanOutReportExport implements WithMultipleSheets
{
    public function __construct(private array $filters = [])
    {
    }

    public function sheets(): array
    {
        return [
            new ScanOutReportSummarySheet($this->filters),
            new ScanOutMissingResiSheet($this->filters),
        ];
    }
}
