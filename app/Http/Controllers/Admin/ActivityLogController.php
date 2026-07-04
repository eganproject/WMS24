<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ActivityLogController extends Controller
{
    public function index()
    {
        $users = User::orderBy('name')->get(['id', 'name']);
        $pages = ActivityLog::query()
            ->whereNotNull('route_name')
            ->where('route_name', '!=', '')
            ->select('route_name')
            ->distinct()
            ->orderBy('route_name')
            ->pluck('route_name')
            ->map(fn (string $routeName) => [
                'value' => $routeName,
                'label' => $this->routeFilterLabel($routeName),
            ]);

        return view('admin.reports.activity-logs.index', [
            'users' => $users,
            'pages' => $pages,
            'dataUrl' => route('admin.reports.activity-logs.data'),
            'detailUrl' => route('admin.reports.activity-logs.show', ':id'),
        ]);
    }

    public function data(Request $request)
    {
        $query = ActivityLog::query()
            ->with('user')
            ->orderBy('created_at', 'desc');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $exact = $this->isExactSearch($request);
            $query->where(function ($q) use ($search, $exact) {
                $this->applyTextSearch($q, 'action', $search, $exact);
                $this->applyTextSearch($q, 'route_name', $search, $exact, 'or');
                $this->applyTextSearch($q, 'url', $search, $exact, 'or');
                $this->applyTextSearch($q, 'method', $search, $exact, 'or');
                $this->applyTextSearch($q, 'ip_address', $search, $exact, 'or');
                $q->orWhereHas('user', function ($userQ) use ($search, $exact) {
                    $this->applyTextSearch($userQ, 'name', $search, $exact);
                    $this->applyTextSearch($userQ, 'email', $search, $exact, 'or');
                });
            });
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->filled('method')) {
            $query->where('method', strtoupper((string) $request->input('method')));
        }

        if ($request->filled('route_name')) {
            $query->where('route_name', (string) $request->input('route_name'));
        }

        $this->applyDateFilter($query, $request);

        $recordsTotal = ActivityLog::count();
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $data = $query->get()->map(function (ActivityLog $log) {
            $createdAt = $log->created_at ? Carbon::parse($log->created_at)->format('Y-m-d H:i:s') : '-';
            $ringkasan = is_array($log->payload) ? ($log->payload['ringkasan'] ?? []) : [];
            return [
                'id' => $log->id,
                'created_at' => $createdAt,
                'user' => $log->user?->name ?? '-',
                'user_email' => $log->user?->email ?? '-',
                'action' => $log->action,
                'modul' => $ringkasan['modul'] ?? '-',
                'hasil' => $ringkasan['hasil'] ?? 'Berhasil',
                'method' => $log->method ?? '-',
                'ip' => $log->ip_address ?? '-',
                'url' => $log->url ?? '-',
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function show(int $id)
    {
        $log = ActivityLog::with('user')->findOrFail($id);
        $payload = is_array($log->payload) ? $log->payload : [];
        $ringkasan = $payload['ringkasan'] ?? [];

        return response()->json([
            'id' => $log->id,
            'created_at' => $log->created_at?->format('Y-m-d H:i:s'),
            'user' => $log->user?->name ?? '-',
            'email' => $log->user?->email ?? '-',
            'action' => $log->action,
            'route_name' => $log->route_name ?? '-',
            'method' => $log->method ?? '-',
            'url' => $log->url ?? '-',
            'ip' => $log->ip_address ?? '-',
            'user_agent' => $log->user_agent ?? '-',
            'modul' => $ringkasan['modul'] ?? '-',
            'hasil' => $ringkasan['hasil'] ?? 'Berhasil',
            'data_utama' => $ringkasan['data_utama'] ?? [],
            'data_dikirim' => $payload['data_dikirim'] ?? $payload,
        ]);
    }

    private function applyDateFilter($query, Request $request): void
    {
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        try {
            if ($dateFrom) {
                $from = Carbon::parse($dateFrom)->startOfDay();
                $query->where('created_at', '>=', $from);
            }
            if ($dateTo) {
                $to = Carbon::parse($dateTo)->endOfDay();
                $query->where('created_at', '<=', $to);
            }
        } catch (\Throwable) {
            // ignore invalid date filters
        }
    }

    private function routeFilterLabel(string $routeName): string
    {
        $routeName = trim($routeName);
        if ($routeName === '') {
            return '-';
        }

        $actionMap = [
            'index' => 'View',
            'data' => 'Data',
            'store' => 'Create',
            'create' => 'Create',
            'update' => 'Update',
            'destroy' => 'Delete',
            'delete' => 'Delete',
            'show' => 'Detail',
            'import' => 'Import',
            'export' => 'Export',
            'approve' => 'Approve',
            'reject' => 'Reject',
        ];

        $segments = explode('.', $routeName);
        $action = array_pop($segments) ?: '';
        $moduleSegments = array_values(array_filter($segments, fn ($segment) => !in_array($segment, ['admin'], true)));

        if (count($moduleSegments) > 1 && in_array($moduleSegments[0], ['attendance', 'reports', 'masterdata', 'inventory', 'inbound', 'outbound'], true)) {
            array_shift($moduleSegments);
        }

        $module = implode(' ', $moduleSegments);
        $module = str_replace(['raw-logs', 'raw_logs'], 'raw-log', $module);
        $module = Str::headline(str_replace(['.', '-', '_'], ' ', $module));
        $module = str_replace('Raw Logs', 'Raw Log', $module);

        $actionLabel = $actionMap[$action] ?? Str::headline(str_replace(['-', '_'], ' ', $action));

        return trim($module.' '.$actionLabel) ?: $routeName;
    }
}
