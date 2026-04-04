<?php

namespace App\Http\Controllers\Admin\ManajemenStok;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\ItemCategory;
use App\Models\StockMovement;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $today = Carbon::now();
        $defaultStart = $today->copy()->startOfMonth();
        $defaultEnd = $today->copy();

        if ($request->ajax()) {
            $searchValue = trim((string) $request->input('search.value', ''));
            $start = (int) $request->input('start', 0);
            $length = (int) $request->input('length', 10);
            $draw = (int) $request->input('draw', 0);
            $itemCategoryFilter = $request->input('item_category_filter');
            $dateFromInput = $request->input('date_from');
            $dateToInput = $request->input('date_to');

            try {
                $dateFrom = $dateFromInput ? Carbon::parse($dateFromInput)->startOfDay() : $defaultStart->copy()->startOfDay();
            } catch (\Throwable $th) {
                $dateFrom = $defaultStart->copy()->startOfDay();
            }

            try {
                $dateTo = $dateToInput ? Carbon::parse($dateToInput)->endOfDay() : $defaultEnd->copy()->endOfDay();
            } catch (\Throwable $th) {
                $dateTo = $defaultEnd->copy()->endOfDay();
            }

            if ($dateFrom->gt($dateTo)) {
                [$dateFrom, $dateTo] = [$dateTo->copy()->startOfDay(), $dateFrom->copy()->endOfDay()];
            }

            $baseQuery = Inventory::query()
                ->join('items', 'inventories.item_id', '=', 'items.id')
                ->when($itemCategoryFilter !== null && $itemCategoryFilter !== '', function ($query) use ($itemCategoryFilter) {
                    $query->where('items.item_category_id', $itemCategoryFilter);
                });

            $totalRecords = (clone $baseQuery)->distinct('inventories.item_id')->count('inventories.item_id');

            $filteredQuery = (clone $baseQuery)
                ->when($searchValue !== '', function ($query) use ($searchValue) {
                    $query->where(function ($inner) use ($searchValue) {
                        $inner->where('items.nama_barang', 'like', "%{$searchValue}%")
                            ->orWhere('items.sku', 'like', "%{$searchValue}%");
                    });
                });

            $recordsFiltered = (clone $filteredQuery)->distinct('inventories.item_id')->count('inventories.item_id');

            $dataQuery = $filteredQuery
                ->select([
                    'items.id as item_id',
                    'items.sku',
                    'items.nama_barang as item_name',
                    DB::raw('SUM(inventories.quantity) as quantity'),
                    DB::raw('SUM(inventories.koli) as koli'),
                ])
                ->groupBy('items.id', 'items.sku', 'items.nama_barang')
                ->addSelect([
                    'within_qty' => StockMovement::query()
                        ->selectRaw('COALESCE(SUM(quantity),0)')
                        ->whereColumn('stock_movements.item_id', 'items.id')
                        ->whereBetween('date', [$dateFrom->copy(), $dateTo->copy()]),
                    'within_koli' => StockMovement::query()
                        ->selectRaw('COALESCE(SUM(koli),0)')
                        ->whereColumn('stock_movements.item_id', 'items.id')
                        ->whereBetween('date', [$dateFrom->copy(), $dateTo->copy()]),
                    'incoming_qty' => StockMovement::query()
                        ->selectRaw('COALESCE(SUM(quantity),0)')
                        ->whereColumn('stock_movements.item_id', 'items.id')
                        ->whereBetween('date', [$dateFrom->copy(), $dateTo->copy()])
                        ->where('quantity', '>', 0),
                    'incoming_koli' => StockMovement::query()
                        ->selectRaw('COALESCE(SUM(koli),0)')
                        ->whereColumn('stock_movements.item_id', 'items.id')
                        ->whereBetween('date', [$dateFrom->copy(), $dateTo->copy()])
                        ->where('quantity', '>', 0),
                    'outgoing_qty' => StockMovement::query()
                        ->selectRaw('COALESCE(SUM(ABS(quantity)),0)')
                        ->whereColumn('stock_movements.item_id', 'items.id')
                        ->whereBetween('date', [$dateFrom->copy(), $dateTo->copy()])
                        ->where('quantity', '<', 0),
                    'outgoing_koli' => StockMovement::query()
                        ->selectRaw('COALESCE(SUM(ABS(koli)),0)')
                        ->whereColumn('stock_movements.item_id', 'items.id')
                        ->whereBetween('date', [$dateFrom->copy(), $dateTo->copy()])
                        ->where('quantity', '<', 0),
                    'after_qty' => StockMovement::query()
                        ->selectRaw('COALESCE(SUM(quantity),0)')
                        ->whereColumn('stock_movements.item_id', 'items.id')
                        ->where('date', '>', $dateTo->copy()),
                    'after_koli' => StockMovement::query()
                        ->selectRaw('COALESCE(SUM(koli),0)')
                        ->whereColumn('stock_movements.item_id', 'items.id')
                        ->where('date', '>', $dateTo->copy()),
                ]);

            $orderDirection = strtolower($request->input('order.0.dir', 'asc')) === 'desc' ? 'desc' : 'asc';

            $data = $dataQuery
                ->orderBy('items.nama_barang', $orderDirection)
                ->offset($start)
                ->limit($length)
                ->get()
                ->map(function ($row) {
                    $quantity = (float) $row->quantity;
                    $koli = (float) $row->koli;
                    $withinQty = (float) ($row->within_qty ?? 0);
                    $withinKoli = (float) ($row->within_koli ?? 0);
                    $afterQty = (float) ($row->after_qty ?? 0);
                    $afterKoli = (float) ($row->after_koli ?? 0);
                    $incomingQty = (float) ($row->incoming_qty ?? 0);
                    $incomingKoli = (float) ($row->incoming_koli ?? 0);
                    $outgoingQty = (float) ($row->outgoing_qty ?? 0);
                    $outgoingKoli = (float) ($row->outgoing_koli ?? 0);

                    $closingQty = $quantity - $afterQty;
                    $closingKoli = $koli - $afterKoli;

                    $openingQty = $closingQty - $withinQty;
                    $openingKoli = $closingKoli - $withinKoli;

                    return [
                        'item_id' => (int) $row->item_id,
                        'item_name' => $row->item_name,
                        'sku' => $row->sku,
                        'opening_qty' => round($openingQty, 2),
                        'opening_koli' => round($openingKoli, 2),
                        'incoming_qty' => round($incomingQty, 2),
                        'incoming_koli' => round($incomingKoli, 2),
                        'outgoing_qty' => round($outgoingQty, 2),
                        'outgoing_koli' => round($outgoingKoli, 2),
                        'closing_qty' => round($closingQty, 2),
                        'closing_koli' => round($closingKoli, 2),
                    ];
                })
                ->values();

            return response()->json([
                'draw' => $draw,
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $recordsFiltered,
                'data' => $data,
            ]);
        }

        $itemCategories = ItemCategory::all();

        return view('admin.manajemenstok.masterstok.index', [
            'itemCategories' => $itemCategories,
            'defaultDateFrom' => $defaultStart->toDateString(),
            'defaultDateTo' => $defaultEnd->toDateString(),
        ]);
    }
}
