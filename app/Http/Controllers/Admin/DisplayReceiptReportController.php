<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DamagedAllocation;
use App\Models\StockMutation;
use App\Models\Warehouse;
use App\Support\WarehouseService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class DisplayReceiptReportController extends Controller
{
    public function index()
    {
        $displayWarehouseId = WarehouseService::displayWarehouseId();

        return view('admin.reports.display-receipts.index', [
            'dataUrl' => route('admin.reports.display-receipts.data'),
            'displayWarehouseId' => $displayWarehouseId,
            'displayWarehouseLabel' => Warehouse::whereKey($displayWarehouseId)->value('name') ?? 'Gudang Display',
            'today' => now()->toDateString(),
        ]);
    }

    public function data(Request $request)
    {
        $displayWarehouseId = WarehouseService::displayWarehouseId();
        $query = $this->baseQuery($displayWarehouseId)
            ->with(['item:id,sku,name', 'creator:id,name']);

        $this->applyDateFilter($query, $request);
        $this->applySearchFilter($query, $request);
        $this->applySourceFilter($query, (string) $request->input('source_group', ''));

        $recordsTotal = $this->baseQuery($displayWarehouseId)->count();
        $recordsFiltered = (clone $query)->count();

        $summaryQuery = clone $query;
        $summary = [
            'total_receipts' => $recordsFiltered,
            'total_qty' => (int) ((clone $summaryQuery)->sum('qty') ?? 0),
            'total_sku' => (int) ((clone $summaryQuery)->distinct('item_id')->count('item_id') ?? 0),
            'rework_qty' => (int) ((clone $summaryQuery)
                ->where('source_type', 'damaged_allocation')
                ->where('source_subtype', 'rework_output')
                ->sum('qty') ?? 0),
        ];

        $query->orderByDesc('occurred_at')->orderByDesc('id');
        $start = max(0, (int) $request->input('start', 0));
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take(min($length, 100));
        }

        $mutations = $query->get();
        $reworkAllocationIds = $mutations
            ->filter(fn (StockMutation $mutation) => $mutation->source_type === 'damaged_allocation' && $mutation->source_subtype === 'rework_output')
            ->pluck('source_id')
            ->filter()
            ->unique()
            ->values();
        $reworkContexts = $reworkAllocationIds->isEmpty()
            ? collect()
            : DamagedAllocation::query()
                ->with(['sourceItems.item:id,sku,name', 'recipe:id,code,name'])
                ->whereIn('id', $reworkAllocationIds)
                ->get()
                ->keyBy('id');

        $data = $mutations->map(function (StockMutation $mutation) use ($reworkContexts) {
            $rework = $reworkContexts->get((int) $mutation->source_id);
            $sourceItems = $rework
                ? $rework->sourceItems
                    ->groupBy('item_id')
                    ->map(function ($rows) {
                        $item = $rows->first()?->item;
                        $label = trim(($item?->sku ?? '-').($item?->name ? ' - '.$item->name : ''));

                        return $label.' ('.(int) $rows->sum('qty').')';
                    })
                    ->values()
                    ->implode(', ')
                : '-';

            return [
                'id' => $mutation->id,
                'occurred_at' => $mutation->occurred_at?->format('Y-m-d H:i') ?? '-',
                'sku' => $mutation->item?->sku ?? '-',
                'item_name' => $mutation->item?->name ?? '-',
                'qty' => (int) $mutation->qty,
                'source_group' => $this->sourceGroupLabel($mutation),
                'source_detail' => $this->sourceDetailLabel($mutation),
                'source_code' => $mutation->source_code ?: '-',
                'rework_source_items' => $sourceItems,
                'rework_recipe' => $rework?->recipe
                    ? trim(($rework->recipe->code ?? '').' - '.($rework->recipe->name ?? ''))
                    : '-',
                'note' => $mutation->note ?? '-',
                'user' => $mutation->creator?->name ?? '-',
                'mutation_url' => route('admin.inventory.stock-mutations.show', $mutation->id),
            ];
        })->values();

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'summary' => $summary,
            'data' => $data,
        ]);
    }

    private function applyDateFilter(Builder $query, Request $request): void
    {
        try {
            if ($request->filled('date_from')) {
                $query->where('occurred_at', '>=', Carbon::parse($request->input('date_from'))->startOfDay());
            }
            if ($request->filled('date_to')) {
                $query->where('occurred_at', '<=', Carbon::parse($request->input('date_to'))->endOfDay());
            }
        } catch (\Throwable) {
            // Abaikan filter tanggal yang tidak valid agar laporan tetap dapat dibuka.
        }
    }

    private function baseQuery(int $displayWarehouseId): Builder
    {
        $query = StockMutation::query()
            ->where('warehouse_id', $displayWarehouseId)
            ->where('direction', 'in');

        // Database lama yang belum menjalankan migrasi void tetap dapat memakai laporan ini.
        if (Schema::hasColumn('stock_mutations', 'is_void')) {
            $query->where('is_void', false);
        }

        return $query;
    }

    private function applySearchFilter(Builder $query, Request $request): void
    {
        $search = trim((string) $request->input('q', ''));
        if ($search === '') {
            return;
        }

        $exact = $this->isExactSearch($request);
        $query->where(function (Builder $q) use ($search, $exact) {
            $this->applyTextSearch($q, 'source_code', $search, $exact);
            $this->applyTextSearch($q, 'source_type', $search, $exact, 'or');
            $this->applyTextSearch($q, 'source_subtype', $search, $exact, 'or');
            $this->applyTextSearch($q, 'note', $search, $exact, 'or');
            $q->orWhereHas('item', function (Builder $itemQuery) use ($search, $exact) {
                $this->applyTextSearch($itemQuery, 'sku', $search, $exact);
                $this->applyTextSearch($itemQuery, 'name', $search, $exact, 'or');
            });
        });
    }

    private function applySourceFilter(Builder $query, string $group): void
    {
        match ($group) {
            'rework' => $query->where('source_type', 'damaged_allocation')->where('source_subtype', 'rework_output'),
            'transfer' => $query->where('source_type', 'transfer'),
            'inbound' => $query->where('source_type', 'inbound'),
            'adjustment' => $query->whereIn('source_type', ['adjustment', 'opname']),
            'return' => $query->where(function (Builder $q) {
                $q->where('source_type', 'customer_return')
                    ->orWhere(function (Builder $damageQuery) {
                        $damageQuery->where('source_type', 'damaged')
                            ->where('source_subtype', 'customer_return');
                    });
            }),
            'other' => $query->whereNot(function (Builder $q) {
                $q->where(function (Builder $reworkQuery) {
                    $reworkQuery->where('source_type', 'damaged_allocation')->where('source_subtype', 'rework_output');
                })->orWhereIn('source_type', ['transfer', 'inbound', 'adjustment', 'opname', 'customer_return'])
                    ->orWhere(function (Builder $damageQuery) {
                        $damageQuery->where('source_type', 'damaged')->where('source_subtype', 'customer_return');
                    });
            }),
            default => null,
        };
    }

    private function sourceGroupLabel(StockMutation $mutation): string
    {
        if ($mutation->source_type === 'damaged_allocation' && $mutation->source_subtype === 'rework_output') return 'Hasil Rework';
        if ($mutation->source_type === 'transfer') return 'Transfer Gudang';
        if ($mutation->source_type === 'inbound') return 'Inbound';
        if (in_array($mutation->source_type, ['adjustment', 'opname'], true)) return 'Penyesuaian Stok';
        if ($mutation->source_type === 'customer_return' || ($mutation->source_type === 'damaged' && $mutation->source_subtype === 'customer_return')) return 'Retur';

        return 'Lainnya';
    }

    private function sourceDetailLabel(StockMutation $mutation): string
    {
        $type = strtoupper(str_replace('_', ' ', (string) $mutation->source_type));
        $subtype = strtoupper(str_replace('_', ' ', (string) $mutation->source_subtype));

        return trim($type.($subtype !== '' ? ' / '.$subtype : ''));
    }
}
