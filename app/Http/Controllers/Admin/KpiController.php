<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\KpiDefinition;
use App\Models\KpiEmployeeAssignment;
use App\Models\KpiScoreItem;
use App\Models\KpiScoreSnapshot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class KpiController extends Controller
{
    public function index()
    {
        $employees = Employee::query()
            ->with('positionRelation')
            ->orderBy('name')
            ->get(['id', 'employee_code', 'name', 'position', 'position_id']);

        $definitions = KpiDefinition::query()
            ->where('is_active', true)
            ->orderBy('role_name')
            ->orderBy('metric_name')
            ->get(['id', 'role_name', 'metric_name', 'target_operator', 'target_value', 'unit']);

        return view('admin.reports.kpi.index', [
            'employees' => $employees,
            'definitions' => $definitions,
            'definitionsDataUrl' => route('admin.reports.kpi.definitions.data'),
            'definitionStoreUrl' => route('admin.reports.kpi.definitions.store'),
            'definitionUpdateUrlTpl' => route('admin.reports.kpi.definitions.update', ':id'),
            'definitionDeleteUrlTpl' => route('admin.reports.kpi.definitions.destroy', ':id'),
            'assignmentsDataUrl' => route('admin.reports.kpi.assignments.data'),
            'assignmentStoreUrl' => route('admin.reports.kpi.assignments.store'),
            'assignmentUpdateUrlTpl' => route('admin.reports.kpi.assignments.update', ':id'),
            'assignmentDeleteUrlTpl' => route('admin.reports.kpi.assignments.destroy', ':id'),
            'snapshotsDataUrl' => route('admin.reports.kpi.snapshots.data'),
            'snapshotStoreUrl' => route('admin.reports.kpi.snapshots.store'),
            'snapshotItemsUrlTpl' => route('admin.reports.kpi.snapshots.items', ':id'),
            'snapshotLockUrlTpl' => route('admin.reports.kpi.snapshots.lock', ':id'),
            'scoreItemUpdateUrlTpl' => route('admin.reports.kpi.score-items.update', ':id'),
        ]);
    }

    public function definitionsData(Request $request)
    {
        $query = KpiDefinition::query()->orderBy('role_name')->orderBy('metric_name');

        $search = trim((string) $request->input('search.value', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('role_name', 'like', "%{$search}%")
                    ->orWhere('metric_name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role_name')) {
            $query->where('role_name', $request->input('role_name'));
        }

        if (in_array($request->input('is_active'), ['0', '1'], true)) {
            $query->where('is_active', (bool) $request->input('is_active'));
        }

        return $this->datatableResponse($request, KpiDefinition::count(), $query, function (KpiDefinition $definition) {
            return [
                'id' => $definition->id,
                'role_name' => $definition->role_name,
                'metric_name' => $definition->metric_name,
                'description' => $definition->description,
                'target_operator' => $definition->target_operator,
                'target_value' => (float) $definition->target_value,
                'unit' => $definition->unit,
                'weight' => (float) $definition->weight,
                'period_type' => $definition->period_type,
                'source_type' => $definition->source_type,
                'formula_key' => $definition->formula_key,
                'is_active' => $definition->is_active,
            ];
        });
    }

    public function storeDefinition(Request $request)
    {
        $data = $this->validateDefinition($request);
        $data['created_by'] = auth()->id();

        $definition = KpiDefinition::create($data);

        return response()->json(['message' => 'KPI berhasil dibuat.', 'id' => $definition->id]);
    }

    public function updateDefinition(Request $request, KpiDefinition $definition)
    {
        $definition->update($this->validateDefinition($request, $definition));

        return response()->json(['message' => 'KPI berhasil diperbarui.']);
    }

    public function destroyDefinition(KpiDefinition $definition)
    {
        $definition->delete();

        return response()->json(['message' => 'KPI berhasil dihapus.']);
    }

    public function assignmentsData(Request $request)
    {
        $query = KpiEmployeeAssignment::query()
            ->with(['employee', 'definition'])
            ->latest('id');

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->integer('employee_id'));
        }

        if ($request->filled('kpi_definition_id')) {
            $query->where('kpi_definition_id', $request->integer('kpi_definition_id'));
        }

        if (in_array($request->input('is_active'), ['0', '1'], true)) {
            $query->where('is_active', (bool) $request->input('is_active'));
        }

        $search = trim((string) $request->input('search.value', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->whereHas('employee', function ($employee) use ($search) {
                    $employee->where('name', 'like', "%{$search}%")
                        ->orWhere('employee_code', 'like', "%{$search}%");
                })->orWhereHas('definition', function ($definition) use ($search) {
                    $definition->where('role_name', 'like', "%{$search}%")
                        ->orWhere('metric_name', 'like', "%{$search}%");
                });
            });
        }

        return $this->datatableResponse($request, KpiEmployeeAssignment::count(), $query, function (KpiEmployeeAssignment $assignment) {
            $definition = $assignment->definition;
            $employee = $assignment->employee;

            return [
                'id' => $assignment->id,
                'employee_id' => $assignment->employee_id,
                'employee' => trim(($employee?->employee_code ? $employee->employee_code.' - ' : '').($employee?->name ?? '-')),
                'kpi_definition_id' => $assignment->kpi_definition_id,
                'role_name' => $definition?->role_name ?? '-',
                'metric_name' => $definition?->metric_name ?? '-',
                'effective_from' => $assignment->effective_from?->format('Y-m-d'),
                'effective_until' => $assignment->effective_until?->format('Y-m-d'),
                'target_value' => $assignment->target_value !== null ? (float) $assignment->target_value : null,
                'default_target_value' => $definition ? (float) $definition->target_value : null,
                'weight' => $assignment->weight !== null ? (float) $assignment->weight : null,
                'default_weight' => $definition ? (float) $definition->weight : null,
                'is_active' => $assignment->is_active,
            ];
        });
    }

    public function storeAssignment(Request $request)
    {
        $data = $this->validateAssignment($request);
        $data['created_by'] = auth()->id();

        $assignment = KpiEmployeeAssignment::create($data);

        return response()->json(['message' => 'Assignment KPI berhasil dibuat.', 'id' => $assignment->id]);
    }

    public function updateAssignment(Request $request, KpiEmployeeAssignment $assignment)
    {
        $assignment->update($this->validateAssignment($request));

        return response()->json(['message' => 'Assignment KPI berhasil diperbarui.']);
    }

    public function destroyAssignment(KpiEmployeeAssignment $assignment)
    {
        $assignment->delete();

        return response()->json(['message' => 'Assignment KPI berhasil dihapus.']);
    }

    public function snapshotsData(Request $request)
    {
        $query = KpiScoreSnapshot::query()
            ->withCount('items')
            ->withAvg('items', 'weighted_score')
            ->latest('period_start')
            ->latest('id');

        if ($request->filled('period_start')) {
            $query->whereDate('period_end', '>=', $request->input('period_start'));
        }

        if ($request->filled('period_end')) {
            $query->whereDate('period_start', '<=', $request->input('period_end'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return $this->datatableResponse($request, KpiScoreSnapshot::count(), $query, function (KpiScoreSnapshot $snapshot) {
            return [
                'id' => $snapshot->id,
                'code' => $snapshot->code,
                'period_start' => $snapshot->period_start?->format('Y-m-d'),
                'period_end' => $snapshot->period_end?->format('Y-m-d'),
                'status' => $snapshot->status,
                'items_count' => $snapshot->items_count,
                'average_score' => round((float) ($snapshot->items_avg_weighted_score ?? 0), 2),
                'note' => $snapshot->note,
                'locked_at' => $snapshot->locked_at?->format('Y-m-d H:i'),
            ];
        });
    }

    public function storeSnapshot(Request $request)
    {
        $data = $request->validate([
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'note' => ['nullable', 'string'],
        ]);

        $snapshot = DB::transaction(function () use ($data) {
            $snapshot = KpiScoreSnapshot::create([
                'code' => $this->nextSnapshotCode($data['period_start']),
                'period_start' => $data['period_start'],
                'period_end' => $data['period_end'],
                'status' => 'draft',
                'note' => $data['note'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $assignments = KpiEmployeeAssignment::query()
                ->with(['definition', 'employee'])
                ->where('is_active', true)
                ->whereDate('effective_from', '<=', $data['period_end'])
                ->where(function ($query) use ($data) {
                    $query->whereNull('effective_until')
                        ->orWhereDate('effective_until', '>=', $data['period_start']);
                })
                ->whereHas('definition', fn ($definition) => $definition->where('is_active', true))
                ->get();

            foreach ($assignments as $assignment) {
                $definition = $assignment->definition;
                if (!$definition) {
                    continue;
                }

                KpiScoreItem::create([
                    'kpi_score_snapshot_id' => $snapshot->id,
                    'kpi_definition_id' => $definition->id,
                    'employee_id' => $assignment->employee_id,
                    'role_name' => $definition->role_name,
                    'metric_name' => $definition->metric_name,
                    'target_operator' => $definition->target_operator,
                    'target_value' => $assignment->target_value ?? $definition->target_value,
                    'actual_value' => null,
                    'achievement_percent' => 0,
                    'score' => 0,
                    'weight' => $assignment->weight ?? $definition->weight,
                    'weighted_score' => 0,
                    'source_type' => $definition->source_type,
                    'formula_key' => $definition->formula_key,
                ]);
            }

            return $snapshot->loadCount('items');
        });

        return response()->json([
            'message' => 'Snapshot KPI berhasil dibuat.',
            'snapshot_id' => $snapshot->id,
            'items_count' => $snapshot->items_count,
        ]);
    }

    public function scoreItemsData(Request $request, KpiScoreSnapshot $snapshot)
    {
        $query = $snapshot->items()
            ->with('employee')
            ->orderBy('role_name')
            ->orderBy('metric_name');

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->integer('employee_id'));
        }

        if ($request->filled('role_name')) {
            $query->where('role_name', $request->input('role_name'));
        }

        $search = trim((string) $request->input('search.value', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('role_name', 'like', "%{$search}%")
                    ->orWhere('metric_name', 'like', "%{$search}%")
                    ->orWhereHas('employee', function ($employee) use ($search) {
                        $employee->where('name', 'like', "%{$search}%")
                            ->orWhere('employee_code', 'like', "%{$search}%");
                    });
            });
        }

        return $this->datatableResponse($request, $snapshot->items()->count(), $query, function (KpiScoreItem $item) use ($snapshot) {
            $employee = $item->employee;

            return [
                'id' => $item->id,
                'employee' => trim(($employee?->employee_code ? $employee->employee_code.' - ' : '').($employee?->name ?? '-')),
                'role_name' => $item->role_name,
                'metric_name' => $item->metric_name,
                'target_operator' => $item->target_operator,
                'target_value' => (float) $item->target_value,
                'actual_value' => $item->actual_value !== null ? (float) $item->actual_value : null,
                'achievement_percent' => (float) $item->achievement_percent,
                'score' => (float) $item->score,
                'weight' => (float) $item->weight,
                'weighted_score' => (float) $item->weighted_score,
                'source_type' => $item->source_type,
                'formula_key' => $item->formula_key,
                'note' => $item->note,
                'snapshot_status' => $snapshot->status,
            ];
        });
    }

    public function updateScoreItem(Request $request, KpiScoreItem $item)
    {
        if ($item->snapshot?->status === 'locked') {
            return response()->json(['message' => 'Snapshot sudah dikunci.'], 422);
        }

        $data = $request->validate([
            'actual_value' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string'],
        ]);

        $result = $this->calculateScore($item->target_operator, (float) $item->target_value, (float) $data['actual_value'], (float) $item->weight);

        $item->update([
            'actual_value' => $data['actual_value'],
            'achievement_percent' => $result['achievement_percent'],
            'score' => $result['score'],
            'weighted_score' => $result['weighted_score'],
            'note' => $data['note'] ?? null,
            'calculated_at' => now(),
        ]);

        return response()->json(['message' => 'Nilai KPI berhasil disimpan.']);
    }

    public function lockSnapshot(KpiScoreSnapshot $snapshot)
    {
        $snapshot->update([
            'status' => 'locked',
            'locked_by' => auth()->id(),
            'locked_at' => now(),
        ]);

        return response()->json(['message' => 'Snapshot KPI berhasil dikunci.']);
    }

    private function validateDefinition(Request $request, ?KpiDefinition $definition = null): array
    {
        return $request->validate([
            'role_name' => ['required', 'string', 'max:100'],
            'metric_name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('kpi_definitions', 'metric_name')
                    ->where(fn ($query) => $query->where('role_name', $request->input('role_name')))
                    ->ignore($definition?->id),
            ],
            'description' => ['nullable', 'string'],
            'target_operator' => ['required', Rule::in(['>=', '<=', '='])],
            'target_value' => ['required', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'weight' => ['required', 'numeric', 'min:0', 'max:100'],
            'period_type' => ['required', Rule::in(['daily', 'weekly', 'monthly', 'quarterly'])],
            'source_type' => ['required', Rule::in(['manual', 'auto'])],
            'formula_key' => ['nullable', 'string', 'max:100'],
            'is_active' => ['required', 'boolean'],
        ]);
    }

    private function validateAssignment(Request $request): array
    {
        return $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'kpi_definition_id' => ['required', 'exists:kpi_definitions,id'],
            'effective_from' => ['required', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'target_value' => ['nullable', 'numeric', 'min:0'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['required', 'boolean'],
        ]);
    }

    private function datatableResponse(Request $request, int $recordsTotal, $query, callable $mapper)
    {
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $query->get()->map($mapper)->values(),
        ]);
    }

    private function nextSnapshotCode(string $periodStart): string
    {
        $prefix = 'KPI-'.date('Ym', strtotime($periodStart));
        $sequence = KpiScoreSnapshot::where('code', 'like', "{$prefix}-%")->count() + 1;

        return $prefix.'-'.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
    }

    private function calculateScore(string $operator, float $target, float $actual, float $weight): array
    {
        if ($operator === '<=') {
            $achievement = $actual <= $target ? 100 : ($target / max($actual, 0.0001)) * 100;
        } elseif ($operator === '=') {
            $achievement = $target == 0.0
                ? ($actual == 0.0 ? 100 : 0)
                : max(0, 100 - (abs($actual - $target) / abs($target) * 100));
        } else {
            $achievement = $target == 0.0 ? ($actual > 0 ? 100 : 0) : ($actual / $target) * 100;
        }

        $score = min(max($achievement, 0), 100);

        return [
            'achievement_percent' => round($achievement, 2),
            'score' => round($score, 2),
            'weighted_score' => round($score * ($weight / 100), 2),
        ];
    }
}
