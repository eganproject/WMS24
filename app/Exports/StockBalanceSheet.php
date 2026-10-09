<?php

namespace App\Exports;

use App\Exports\Concerns\StylesReportSheet;
use App\Support\StockBalanceExportReport;
use Maatwebsite\Excel\DefaultValueBinder;

abstract class StockBalanceSheet extends DefaultValueBinder
{
    use StylesReportSheet;

    /** Angka kosong tetap tampil 0; minus berwarna merah. */
    protected const QTY_FORMAT = '#,##0;[Red]-#,##0;0';
    protected const SIGNED_QTY_FORMAT = '+#,##0;[Red]-#,##0;0';

    public function __construct(protected StockBalanceExportReport $report) {}
}
