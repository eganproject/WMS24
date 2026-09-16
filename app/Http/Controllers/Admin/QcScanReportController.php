<?php

namespace App\Http\Controllers\Admin;

use App\Exports\QcScanReportExport;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\QcScanReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class QcScanReportController extends Controller
{
    public function index()
    {
        $operatorIds = DB::table('qc_resi_scans')
            ->selectRaw('COALESCE(completed_by, scanned_by) as operator_id')
            ->whereNotNull('completed_at')
            ->distinct()
            ->pluck('operator_id')
            ->filter();

        return view('admin.reports.qc-scan.index', [
            'dataUrl' => route('admin.reports.qc-scan.data'),
            'exportUrl' => route('admin.reports.qc-scan.export'),
            'operators' => User::query()->whereIn('id', $operatorIds)->orderBy('name')->get(['id', 'name']),
            'today' => now()->toDateString(),
        ]);
    }

    public function data(Request $request, QcScanReport $report)
    {
        $filters = $this->validatedFilters($request);
        if (!empty($filters['operator_id'])) {
            $filters['operator_name'] = User::query()->whereKey($filters['operator_id'])->value('name') ?? '';
        }

        return response()->json($report->build($filters));
    }

    public function export(Request $request, QcScanReport $report)
    {
        $filters = $this->validatedFilters($request);
        if (!empty($filters['operator_id'])) {
            $filters['operator_name'] = User::query()->whereKey($filters['operator_id'])->value('name') ?? '';
        }

        $data = $report->build($filters);

        return Excel::download(
            new QcScanReportExport($data),
            'laporan-qc-scan-'.now()->format('Ymd_His').'.xlsx'
        );
    }

    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'operator_id' => ['nullable', 'integer', 'exists:users,id'],
            'q' => ['nullable', 'string', 'max:120'],
        ]);
    }
}
