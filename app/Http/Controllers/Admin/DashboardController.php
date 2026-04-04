<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\StockInOrder;
use App\Models\StockOut;
use App\Models\TransferRequest;
use App\Models\StockMovement;
use App\Models\GoodsReceipt;
use App\Models\User;
use App\Models\UserActivity;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $warehouseId = $user->warehouse_id;
        $lowThreshold = 10; // TODO: make configurable
        $highThreshold = 1000; // TODO: make configurable

        if ($warehouseId) {
            // Data for users with a warehouse
            $recentActivities = UserActivity::where('user_id', $user->id)
                ->latest()
                ->take(5)
                ->get();

            // Stock-in today: sum only from movements linked to stock_in_order_items
            $todayStockIn = StockMovement::where('warehouse_id', $warehouseId)
                ->where('type', 'stock_in')
                ->where('reference_type', 'stock_in_order_items')
                ->whereDate('date', today())
                ->sum('quantity');

            // Stock-out today: sum only from movements linked to stock_out_items
            $todayStockOut = StockMovement::where('warehouse_id', $warehouseId)
                ->where('type', 'stock_out')
                ->where('reference_type', 'stock_out_items')
                ->whereDate('date', today())
                ->sum('quantity');

            // Incoming transfers en route to this warehouse
            $pendingTransfers = TransferRequest::where('to_warehouse_id', $warehouseId)
                ->where('status', 'on_progress')
                ->count();

            // Stock reminders (warehouse scoped)
            $zeroStockItems = Inventory::where('warehouse_id', $warehouseId)
                ->where('quantity', '<=', 0)
                ->count();
            $lowStockItems = Inventory::where('warehouse_id', $warehouseId)
                ->where('quantity', '>', 0)
                ->where('quantity', '<=', $lowThreshold)
                ->count();
            $optimalStockItems = Inventory::where('warehouse_id', $warehouseId)
                ->where('quantity', '>', $lowThreshold)
                ->where('quantity', '<=', $highThreshold)
                ->count();
            $overStockItems = Inventory::where('warehouse_id', $warehouseId)
                ->where('quantity', '>', $highThreshold)
                ->count();

            $stockChartData = $this->getStockChartData($warehouseId);

            // Stock summary (warehouse scoped)
            $invScope = Inventory::where('warehouse_id', $warehouseId);
            $totalSkus = (clone $invScope)->distinct('item_id')->count('item_id');

            // Stock In status counts (warehouse scoped)
            $stockInRequested = StockInOrder::where('warehouse_id', $warehouseId)->where('status', 'requested')->count();
            $stockInShipped = StockInOrder::where('warehouse_id', $warehouseId)->where('status', 'on_progress')->count();
            $stockInCompleted = StockInOrder::where('warehouse_id', $warehouseId)->where('status', 'completed')->count();

            // Recent movements & low stock list
            $recentMovements = StockMovement::with('item')
                ->where('warehouse_id', $warehouseId)
                ->latest('id')
                ->take(5)
                ->get();
            $topLowStocks = Inventory::with('item')
                ->where('warehouse_id', $warehouseId)
                ->orderBy('quantity', 'asc')
                ->take(5)
                ->get();

            return view('admin.dashboard.index', compact(
                'user',
                'recentActivities',
                'todayStockIn',
                'todayStockOut',
                'pendingTransfers',
                'lowStockItems',
                'zeroStockItems',
                'optimalStockItems',
                'overStockItems',
                'stockChartData',
                'totalSkus',
                'stockInRequested',
                'stockInShipped',
                'stockInCompleted',
                'recentMovements',
                'topLowStocks'
            ));
        } else {
            // Data for users without a warehouse (system-wide)
            $totalUsers = User::count();
            $totalWarehouses = Warehouse::count();
            $todayStockIn = StockMovement::where('type', 'stock_in')
                ->where('reference_type', 'stock_in_order_items')
                ->whereDate('date', today())
                ->sum('quantity');
            $todayStockOut = StockMovement::where('type', 'stock_out')
                ->where('reference_type', 'stock_out_items')
                ->whereDate('date', today())
                ->sum('quantity');
            $recentActivities = UserActivity::with('user')->latest()->take(5)->get();

            // Stock reminders (system-wide)
            $zeroStockItems = Inventory::where('quantity', '<=', 0)->count();
            $lowStockItems = Inventory::where('quantity', '>', 0)->where('quantity', '<=', $lowThreshold)->count();
            $optimalStockItems = Inventory::where('quantity', '>', $lowThreshold)->where('quantity', '<=', $highThreshold)->count();
            $overStockItems = Inventory::where('quantity', '>', $highThreshold)->count();
            $stockChartData = $this->getStockChartData(null);

            // Stock summary (system-wide)
            $totalSkus = Inventory::distinct('item_id')->count('item_id');

            $stockInRequested = StockInOrder::where('status', 'requested')->count();
            $stockInShipped = StockInOrder::where('status', 'on_progress')->count();
            $stockInCompleted = StockInOrder::where('status', 'completed')->count();

            $recentMovements = StockMovement::with('item')->latest('id')->take(5)->get();
            $topLowStocks = Inventory::with('item')->orderBy('quantity', 'asc')->take(5)->get();

            return view('admin.dashboard.index', compact(
                'user',
                'totalUsers',
                'totalWarehouses',
                'todayStockIn',
                'todayStockOut',
                'recentActivities',
                'zeroStockItems',
                'lowStockItems',
                'optimalStockItems',
                'overStockItems',
                'stockChartData',
                'totalSkus',
                'stockInRequested',
                'stockInShipped',
                'stockInCompleted',
                'recentMovements',
                'topLowStocks'
            ));
        }
    }

    private function getStockChartData($warehouseId)
    {
        $dates = collect();
        for ($i = 6; $i >= 0; $i--) {
            $dates->push(today()->subDays($i)->toDateString());
        }

        // Use completed Goods Receipts to represent actual stock-in events
        $stockInQuery = GoodsReceipt::where('status', GoodsReceipt::STATUS_COMPLETED)
            ->whereBetween('completed_at', [today()->subDays(6), today()->endOfDay()]);

        $stockOutQuery = StockOut::whereBetween('date', [today()->subDays(6), today()->endOfDay()]);

        if ($warehouseId) {
            $stockInQuery->where('warehouse_id', $warehouseId);
            $stockOutQuery->where('warehouse_id', $warehouseId);
        }

        $stockIn = $stockInQuery->select(DB::raw('DATE(completed_at) as date'), DB::raw('count(*) as count'))
            ->groupBy(DB::raw('DATE(completed_at)'))
            ->pluck('count', 'date');

        $stockOut = $stockOutQuery->select(DB::raw('DATE(date) as date'), DB::raw('count(*) as count'))
            ->groupBy(DB::raw('DATE(date)'))
            ->pluck('count', 'date');

        $chartData = [
            'labels' => $dates->map(function ($date) {
                return date('d M', strtotime($date));
            }),
            'stock_in' => $dates->map(function ($date) use ($stockIn) {
                return $stockIn->get($date, 0);
            }),
            'stock_out' => $dates->map(function ($date) use ($stockOut) {
                return $stockOut->get($date, 0);
            }),
        ];

        return $chartData;
    }
}
