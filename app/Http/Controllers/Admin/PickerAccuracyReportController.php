<?php

namespace App\Http\Controllers\Admin;

use App\Exports\PickerAccuracyReportExport;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Support\PickerAccuracyReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class PickerAccuracyReportController extends Controller
{
    public function index()
    {
        $pickerIds = DB::table('qc_resi_scans')
            ->whereNotNull('picker_employee_id')
            ->distinct()
            ->pluck('picker_employee_id');

        return view('admin.reports.picker-accuracy.index', [
            'dataUrl' => route('admin.reports.picker-accuracy.data'),
            'exportUrl' => route('admin.reports.picker-accuracy.export'),
            'pickers' => Employee::query()->whereIn('id', $pickerIds)->orderBy('name')->get(['id', 'employee_code', 'name']),
            'today' => now()->toDateString(),
            'monthStart' => now()->startOfMonth()->toDateString(),
        ]);
    }

    public function data(Request $request, PickerAccuracyReport $report)
    {
        return response()->json($report->build($this->validatedFilters($request)));
    }

    public function export(Request $request, PickerAccuracyReport $report)
    {
        return Excel::download(
            new PickerAccuracyReportExport($report->build($this->validatedFilters($request))),
            'laporan-akurasi-picker-'.now()->format('Ymd_His').'.xlsx'
        );
    }

    private function validatedFilters(Request $request): array
    {
        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'picker_id' => ['nullable', 'integer', 'exists:employees,id'],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        if (!empty($filters['picker_id'])) {
            $filters['picker_name'] = Employee::query()->whereKey($filters['picker_id'])->value('name') ?? '';
        }

        return $filters;
    }
}
