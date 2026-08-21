<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class InboundReceiptsExport implements WithMultipleSheets
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

        return $this->sheets = [
            new InboundReceiptsOverviewSheet($this->filters),
            new InboundReceiptsSummarySheet($this->filters),
            new InboundReceiptsDetailSheet($this->filters),
        ];
    }
}
