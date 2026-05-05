<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Warehouse;
use App\Support\WarehouseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReplenishmentReportController extends Controller
{
    public function index()
    {
        $categories = Category::orderBy('name')->get(['id', 'name']);
        $defaultId = WarehouseService::defaultWarehouseId();
        $displayId = WarehouseService::displayWarehouseId();
        $defaultLabel = Warehouse::where('id', $defaultId)->value('name') ?? 'Gudang Besar';
        $displayLabel = Warehouse::where('id', $displayId)->value('name') ?? 'Gudang Display';

        return view('admin.reports.replenishment.index', [
            'dataUrl' => route('admin.reports.replenishment.data'),
            'categories' => $categories,
            'defaultWarehouseLabel' => $defaultLabel,
            'displayWarehouseLabel' => $displayLabel,
            'defaultWarehouseId' => $defaultId,
            'displayWarehouseId' => $displayId,
            'transferUrl' => route('admin.inventory.stock-transfers.index'),
        ]);
    }

    public function data(Request $request)
    {
        $defaultId = WarehouseService::defaultWarehouseId();
        $displayId = WarehouseService::displayWarehouseId();

        $displaySafetyExpr = 'COALESCE(sd.safety_stock, i.safety_stock, 0)';
        $mainSafetyExpr = 'COALESCE(sm.safety_stock, i.safety_stock, 0)';
        $displayStockExpr = 'COALESCE(sd.stock, 0)';
        $mainStockExpr = 'COALESCE(sm.stock, 0)';
        $koliQtyExpr = 'COALESCE(i.koli_qty, 0)';
        $needExpr = "CASE WHEN {$displaySafetyExpr} > {$displayStockExpr} THEN {$displaySafetyExpr} - {$displayStockExpr} ELSE 0 END";
        $mainAvailableExpr = "CASE WHEN {$mainStockExpr} > {$mainSafetyExpr} THEN {$mainStockExpr} - {$mainSafetyExpr} ELSE 0 END";
        $needModExpr = "({$needExpr} % NULLIF({$koliQtyExpr}, 0))";
        $mainAvailableModExpr = "({$mainAvailableExpr} % NULLIF({$koliQtyExpr}, 0))";
        $needRoundedExpr = "CASE
            WHEN {$koliQtyExpr} <= 0 OR {$needExpr} <= 0 THEN {$needExpr}
            WHEN {$needModExpr} = 0 THEN {$needExpr}
            ELSE {$needExpr} + ({$koliQtyExpr} - {$needModExpr})
        END";
        $mainAvailableRoundedExpr = "CASE
            WHEN {$koliQtyExpr} <= 0 OR {$mainAvailableExpr} <= 0 THEN {$mainAvailableExpr}
            WHEN {$mainAvailableModExpr} = 0 THEN {$mainAvailableExpr}
            ELSE {$mainAvailableExpr} - {$mainAvailableModExpr}
        END";
        $suggestExpr = "CASE
            WHEN {$needExpr} <= 0 OR {$mainAvailableExpr} <= 0 THEN 0
            WHEN {$koliQtyExpr} <= 0 THEN CASE WHEN {$needExpr} < {$mainAvailableExpr} THEN {$needExpr} ELSE {$mainAvailableExpr} END
            WHEN {$needRoundedExpr} < {$mainAvailableRoundedExpr} THEN {$needRoundedExpr}
            ELSE {$mainAvailableRoundedExpr}
        END";

        $baseQuery = DB::table('items as i')
            ->leftJoin('item_stocks as sd', function ($join) use ($displayId) {
                $join->on('sd.item_id', '=', 'i.id')
                    ->where('sd.warehouse_id', '=', $displayId);
            })
            ->leftJoin('item_stocks as sm', function ($join) use ($defaultId) {
                $join->on('sm.item_id', '=', 'i.id')
                    ->where('sm.warehouse_id', '=', $defaultId);
            })
            ->leftJoin('categories as c', 'c.id', '=', 'i.category_id')
            ->where(function ($query) {
                $query->whereNull('i.item_type')
                    ->orWhere('i.item_type', '!=', 'bundle');
            })
            ->whereRaw("{$displaySafetyExpr} > 0")
            ->whereRaw("{$displayStockExpr} < {$displaySafetyExpr}");

        $catFilter = $request->input('category_id');
        if ($catFilter !== null && $catFilter !== '') {
            if ((int) $catFilter === 0) {
                $baseQuery->where('i.category_id', 0);
            } else {
                $baseQuery->where('i.category_id', (int) $catFilter);
            }
        }

        $recordsTotalQuery = clone $baseQuery;

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $exact = $this->isExactSearch($request);
            $baseQuery->where(function ($q) use ($search, $exact) {
                $this->applyTextSearch($q, 'i.sku', $search, $exact);
                $this->applyTextSearch($q, 'i.name', $search, $exact, 'or');
                $this->applyTextSearch($q, 'i.address', $search, $exact, 'or');
                $this->applyTextSearch($q, 'i.description', $search, $exact, 'or');
            });
        }

        $recordsTotal = (clone $recordsTotalQuery)->count();
        $recordsFiltered = (clone $baseQuery)->count();

        $summaryNeed = (int) ((clone $baseQuery)->selectRaw("COALESCE(SUM({$needExpr}), 0) as total_need")->value('total_need') ?? 0);
        $summarySuggest = (int) ((clone $baseQuery)->selectRaw("COALESCE(SUM({$suggestExpr}), 0) as total_suggest")->value('total_suggest') ?? 0);

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);

        $dataQuery = clone $baseQuery;
        $dataQuery->select([
            'i.id',
            'i.sku',
            'i.name',
            'i.address',
            DB::raw("{$koliQtyExpr} as koli_qty"),
            DB::raw("{$displaySafetyExpr} as safety_stock"),
            DB::raw("{$displayStockExpr} as display_stock"),
            DB::raw("{$mainStockExpr} as main_stock"),
            DB::raw("{$mainSafetyExpr} as main_safety_stock"),
            DB::raw("{$mainAvailableExpr} as available_main_qty"),
            DB::raw("{$mainAvailableRoundedExpr} as available_main_rounded_qty"),
            DB::raw("{$needExpr} as need_qty"),
            DB::raw("{$needRoundedExpr} as need_rounded_qty"),
            DB::raw("{$suggestExpr} as suggest_qty"),
            DB::raw("CASE WHEN sd.safety_stock IS NOT NULL THEN 'Per gudang' ELSE 'Default item' END as display_safety_source"),
            DB::raw("CASE WHEN sm.safety_stock IS NOT NULL THEN 'Per gudang' ELSE 'Default item' END as main_safety_source"),
            DB::raw("CASE WHEN i.category_id = 0 THEN 'Tanpa Kategori' ELSE COALESCE(c.name, '-') END as category"),
        ])
        ->orderByRaw("{$needExpr} desc")
        ->orderBy('i.sku');

        if ($length > 0) {
            $dataQuery->skip($start)->take($length);
        }

        $data = $dataQuery->get()->map(function ($row) {
            return [
                'id' => $row->id,
                'sku' => $row->sku ?? '-',
                'name' => $row->name ?? '-',
                'category' => $row->category ?? '-',
                'address' => $row->address ?? '-',
                'koli_qty' => (int) ($row->koli_qty ?? 0),
                'display_stock' => (int) ($row->display_stock ?? 0),
                'safety_stock' => (int) ($row->safety_stock ?? 0),
                'display_safety_source' => $row->display_safety_source ?? 'Default item',
                'need_qty' => (int) ($row->need_qty ?? 0),
                'need_rounded_qty' => (int) ($row->need_rounded_qty ?? 0),
                'main_stock' => (int) ($row->main_stock ?? 0),
                'main_safety_stock' => (int) ($row->main_safety_stock ?? 0),
                'available_main_qty' => (int) ($row->available_main_qty ?? 0),
                'available_main_rounded_qty' => (int) ($row->available_main_rounded_qty ?? 0),
                'main_safety_source' => $row->main_safety_source ?? 'Default item',
                'suggest_qty' => (int) ($row->suggest_qty ?? 0),
                'suggest_koli' => (int) ($row->koli_qty ?? 0) > 0
                    ? (int) floor(((int) ($row->suggest_qty ?? 0)) / ((int) $row->koli_qty))
                    : 0,
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'summary' => [
                'total_items' => $recordsFiltered,
                'total_need' => $summaryNeed,
                'total_suggest' => $summarySuggest,
            ],
            'data' => $data,
        ]);
    }
}
