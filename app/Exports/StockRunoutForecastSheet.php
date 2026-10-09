<?php

namespace App\Exports;

use App\Exports\Concerns\StylesReportSheet;
use App\Support\StockRunoutForecastReport;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\DefaultValueBinder;

abstract class StockRunoutForecastSheet extends DefaultValueBinder
{
    use StylesReportSheet;

    /** Angka kosong tetap tampil 0; minus berwarna merah. */
    protected const QTY_FORMAT = '#,##0;[Red]-#,##0;0';
    protected const DECIMAL_FORMAT = '#,##0.00;[Red]-#,##0.00;0';
    protected const DAYS_FORMAT = '#,##0.0" hari"';

    /** Warna status: label, latar, teks. */
    protected const STATUS_COLORS = [
        StockRunoutForecastReport::STATUS_EMPTY => ['FFF5F8', 'D9214E'],
        StockRunoutForecastReport::STATUS_CRITICAL => ['FFF8DD', '946200'],
        StockRunoutForecastReport::STATUS_WARNING => ['EAF4FF', '0063B1'],
    ];

    /** @param Collection<int, array> $rows */
    public function __construct(protected StockRunoutForecastReport $report, protected Collection $rows) {}

    protected function statusHighlightRules(): array
    {
        return collect(self::STATUS_COLORS)
            ->map(fn (array $colors, string $status) => [StockRunoutForecastReport::STATUS_LABELS[$status], ...$colors])
            ->values()
            ->all();
    }
}
