<?php

namespace App\Http\Controllers\Admin;

use App\Exports\InboundLeadTimeReportExport;
use App\Http\Controllers\Controller;
use App\Support\InboundLeadTimeReport;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class InboundLeadTimeReportController extends Controller
{
    public function index()
    {
        return view('admin.reports.inbound-lead-time.index', [
            'dataUrl' => route('admin.reports.inbound-lead-time.data'),
            'exportUrl' => route('admin.reports.inbound-lead-time.export'),
            'today' => now()->toDateString(),
        ]);
    }

    public function data(Request $request, InboundLeadTimeReport $report)
    {
        return response()->json($report->build($this->validatedFilters($request)));
    }

    public function export(Request $request, InboundLeadTimeReport $report)
    {
        $data = $report->build($this->validatedFilters($request));

        return Excel::download(
            new InboundLeadTimeReportExport($data),
            'laporan-lead-time-inbound-'.now()->format('Ymd_His').'.xlsx'
        );
    }

    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'type' => ['nullable', Rule::in(['receipt', 'return', 'manual', 'opening'])],
            'status' => ['nullable', Rule::in(['pending_scan', 'scanning', 'completed'])],
            'q' => ['nullable', 'string', 'max:120'],
        ]);
    }
}
