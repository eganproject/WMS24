<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class InboundReceiptsExport implements WithMultipleSheets
{
    public function __construct(private array $filters = [])
    {
    }

    public function sheets(): array
    {
        return [
            new InboundReceiptsSummarySheet($this->filters),
            new InboundReceiptsDetailSheet($this->filters),
        ];
    }
}
