<?php

namespace App\Http\Controllers\Admin;

use App\Exports\StockRunoutForecastExport;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Support\StockRunoutForecastReport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class StockRunoutForecastController extends Controller
{
    public function index()
    {
        return view('admin.reports.stock-runout-forecast.index', [
            'dataUrl' => route('admin.reports.stock-runout-forecast.data'),
            'exportUrl' => route('admin.reports.stock-runout-forecast.export'),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'sortOptions' => StockRunoutForecastReport::SORT_LABELS,
            'criticalDays' => StockRunoutForecastReport::CRITICAL_DAYS,
        ]);
    }

    public function data(Request $request)
    {
        $report = StockRunoutForecastReport::fromRequest($request);
        $recordsFiltered = $report->count();
        $start = max(0, (int) $request->input('start', 0));
        $length = min(100, max(1, (int) $request->input('length', 25)));

        return response()->json([
            'period' => [
                'history_days' => $report->historyDays,
                'forecast_days' => $report->forecastDays,
                'start' => $report->periodStart->toDateString(),
                'end' => $report->periodEnd->toDateString(),
            ],
            'summary' => $report->summary(),
            'status_counts' => $report->statusBreakdown(),
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsFiltered,
            'recordsFiltered' => $recordsFiltered,
            'data' => $report->rows($start, $length),
        ]);
    }

    public function export(Request $request)
    {
        $report = StockRunoutForecastReport::fromRequest($request);
        $filename = sprintf(
            'forecast-ketahanan-stok-%dh-histori-%dh-forecast-%s.xlsx',
            $report->historyDays,
            $report->forecastDays,
            now()->format('Ymd-His')
        );

        return Excel::download(new StockRunoutForecastExport($report), $filename);
    }
}
