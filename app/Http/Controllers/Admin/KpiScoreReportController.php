<?php

namespace App\Http\Controllers\Admin;

use App\Exports\KpiScoreReportExport;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\KpiScoreItem;
use App\Models\KpiScoreSnapshot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class KpiScoreReportController extends Controller
{
    public function index()
    {
        return view('admin.reports.kpi-score.index', [
            'dataUrl' => route('admin.reports.kpi-score.data'),
            'summaryUrl' => route('admin.reports.kpi-score.summary'),
            'exportUrl' => route('admin.reports.kpi-score.export'),
            'snapshots' => KpiScoreSnapshot::query()
                ->latest('period_start')
                ->latest('id')
                ->get(['id', 'code', 'period_start', 'period_end', 'status']),
            'employees' => Employee::query()
                ->orderBy('name')
                ->get(['id', 'employee_code', 'name']),
            'roles' => KpiScoreItem::query()
                ->select('role_name')
                ->distinct()
                ->orderBy('role_name')
                ->pluck('role_name'),
            'defaultDateFrom' => now()->startOfMonth()->toDateString(),
            'defaultDateTo' => now()->toDateString(),
        ]);
    }

    public function data(Request $request)
    {
        $query = KpiScoreItem::query()
            ->with(['snapshot:id,code,period_start,period_end,status', 'employee:id,employee_code,name']);

        $this->applyFilters($query, $request);

        $recordsFiltered = (clone $query)->count();
        $start = max((int) $request->input('start', 0), 0);
        $length = (int) $request->input('length', 25);

        $dataQuery = clone $query;
        $dataQuery
            ->join('kpi_score_snapshots', 'kpi_score_snapshots.id', '=', 'kpi_score_items.kpi_score_snapshot_id')
            ->select('kpi_score_items.*')
            ->orderByDesc('kpi_score_snapshots.period_start')
            ->orderBy('kpi_score_items.role_name')
            ->orderBy('kpi_score_items.metric_name');

        if ($length > 0) {
            $dataQuery->skip($start)->take($length);
        }

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsFiltered,
            'recordsFiltered' => $recordsFiltered,
            'data' => $dataQuery->get()->map(fn (KpiScoreItem $item) => $this->mapRow($item))->values(),
        ]);
    }

    public function summary(Request $request)
    {
        $query = KpiScoreItem::query()->with('snapshot:id,status');
        $this->applyFilters($query, $request);

        $rows = $query->get();
        $total = $rows->count();
        $actualFilled = $rows->whereNotNull('actual_value')->count();
        $locked = $rows->filter(fn (KpiScoreItem $row) => $row->snapshot?->status === 'locked')->count();

        return response()->json([
            'total_items' => $total,
            'actual_filled' => $actualFilled,
            'actual_pending' => max(0, $total - $actualFilled),
            'locked_items' => $locked,
            'avg_score' => round((float) $rows->avg('score'), 2),
            'avg_weighted_score' => round((float) $rows->avg('weighted_score'), 2),
            'completed_rate' => $total > 0 ? round(($actualFilled / $total) * 100, 2) : 0,
        ]);
    }

    public function export(Request $request)
    {
        $filename = 'laporan-kpi-score-'.now()->format('Ymd-His').'.xlsx';

        return Excel::download(new KpiScoreReportExport($request->query()), $filename);
    }

    public function applyFilters(Builder $query, Request $request): void
    {
        $query->whereHas('snapshot', function (Builder $snapshot) use ($request) {
            if ($request->filled('date_from')) {
                $snapshot->whereDate('period_end', '>=', $request->input('date_from'));
            }

            if ($request->filled('date_to')) {
                $snapshot->whereDate('period_start', '<=', $request->input('date_to'));
            }

            if ($request->filled('snapshot_id')) {
                $snapshot->whereKey($request->integer('snapshot_id'));
            }

            if ($request->filled('status')) {
                $snapshot->where('status', $request->input('status'));
            }
        });

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->integer('employee_id'));
        }

        if ($request->filled('role_name')) {
            $query->where('role_name', $request->input('role_name'));
        }

        if ($request->filled('source_type')) {
            $query->where('source_type', $request->input('source_type'));
        }

        if ($request->filled('actual_status')) {
            if ($request->input('actual_status') === 'filled') {
                $query->whereNotNull('actual_value');
            } elseif ($request->input('actual_status') === 'pending') {
                $query->whereNull('actual_value');
            }
        }

        $search = trim((string) $request->input('search.value', $request->input('q', '')));
        if ($search !== '') {
            $query->where(function (Builder $q) use ($search) {
                $q->where('role_name', 'like', "%{$search}%")
                    ->orWhere('metric_name', 'like', "%{$search}%")
                    ->orWhere('formula_key', 'like', "%{$search}%")
                    ->orWhereHas('employee', function (Builder $employee) use ($search) {
                        $employee->where('name', 'like', "%{$search}%")
                            ->orWhere('employee_code', 'like', "%{$search}%");
                    })
                    ->orWhereHas('snapshot', fn (Builder $snapshot) => $snapshot->where('code', 'like', "%{$search}%"));
            });
        }
    }

    public function mapRow(KpiScoreItem $item): array
    {
        $employee = $item->employee;
        $snapshot = $item->snapshot;

        return [
            'snapshot_code' => $snapshot?->code ?? '-',
            'period' => trim(($snapshot?->period_start?->format('Y-m-d') ?? '-').' s/d '.($snapshot?->period_end?->format('Y-m-d') ?? '-')),
            'snapshot_status' => $snapshot?->status ?? '-',
            'employee' => trim(($employee?->employee_code ? $employee->employee_code.' - ' : '').($employee?->name ?? '-')),
            'role_name' => $item->role_name,
            'metric_name' => $item->metric_name,
            'target' => $item->target_operator.' '.number_format((float) $item->target_value, 4, ',', '.'),
            'actual_value' => $item->actual_value !== null ? (float) $item->actual_value : null,
            'achievement_percent' => (float) $item->achievement_percent,
            'score' => (float) $item->score,
            'weight' => (float) $item->weight,
            'weighted_score' => (float) $item->weighted_score,
            'source_type' => $item->source_type,
            'formula_key' => $item->formula_key,
            'calculated_at' => $item->calculated_at?->format('Y-m-d H:i') ?? '-',
            'note' => $item->note,
        ];
    }
}
