<?php

namespace App\Exports;

use App\Support\OutboundManualReport;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class OutboundManualExport implements WithMultipleSheets
{
    private ?array $sheets = null;

    public function __construct(private array $filters = [])
    {
    }

    public function sheets(): array
    {
        if ($this->sheets !== null) {
            return $this->sheets;
        }

        $report = new OutboundManualReport($this->filters);

        return $this->sheets = [
            new OutboundManualOverviewSheet($report),
            new OutboundManualItemSummarySheet($report),
            new OutboundManualDocumentsSheet($report),
            new OutboundManualDetailSheet($report),
        ];
    }
}
