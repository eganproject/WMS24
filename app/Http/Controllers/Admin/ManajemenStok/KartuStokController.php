<?php

namespace App\Http\Controllers\Admin\ManajemenStok;

use App\Exports\KartuStokExport;
use App\Http\Controllers\Controller;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Models\Item;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class KartuStokController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $start = (int) $request->input('start', 0);
            $length = (int) $request->input('length', 10);
            $draw = (int) $request->input('draw', 0);

            [$baseQuery, $recordsTotal, $recordsFiltered] = $this->buildQuery($request);

            $data = (clone $baseQuery)
                ->orderBy('sm.date', 'desc')
                ->orderBy('sm.id', 'desc')
                ->offset($start)
                ->limit($length)
                ->get();

            return response()->json([
                'draw' => $draw,
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data' => $data,
            ]);
        }

        $warehouses = auth()->user()->warehouse_id
            ? Warehouse::where('id', auth()->user()->warehouse_id)->get()
            : Warehouse::all();

        $items = Item::orderBy('nama_barang')->get(['id', 'nama_barang', 'sku']);

        return view('admin.manajemenstok.kartustok.index', compact('warehouses', 'items'));
    }

    public function export(Request $request)
    {
        [$baseQuery] = $this->buildQuery($request);
        $showWarehouseColumn = !auth()->user()->warehouse_id;

        $rows = (clone $baseQuery)
            ->orderBy('sm.date', 'desc')
            ->orderBy('sm.id', 'desc')
            ->get();

        $headings = ['Tanggal'];
        if ($showWarehouseColumn) {
            $headings[] = 'Gudang';
        }
        $headings = array_merge($headings, [
            'SKU',
            'Keterangan',
            'Stok Awal (Qty)',
            'Stok Awal (Koli)',
            'Stok Masuk (Qty)',
            'Stok Masuk (Koli)',
            'Stok Keluar (Qty)',
            'Stok Keluar (Koli)',
            'Stok Akhir (Qty)',
            'Stok Akhir (Koli)',
            'User',
        ]);

        $dataRows = [];
        foreach ($rows as $row) {
            $line = [
                $row->date,
            ];

            if ($showWarehouseColumn) {
                $line[] = $row->warehouse_name ?? '';
            }

            $line = array_merge($line, [
                $row->sku_name ?? '',
                $row->description ?? '',
                (float) ($row->stock_before ?? 0),
                (float) ($row->stock_before_koli ?? 0),
                (float) ($row->stock_in ?? 0),
                (float) ($row->stock_in_koli ?? 0),
                (float) ($row->stock_out ?? 0),
                (float) ($row->stock_out_koli ?? 0),
                (float) ($row->stock_after ?? 0),
                (float) ($row->stock_after_koli ?? 0),
                $row->user_name ?? '',
            ]);

            $dataRows[] = $line;
        }

        $fileName = 'kartu_stok_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(new KartuStokExport($dataRows, $headings), $fileName);
    }

    private function buildQuery(Request $request): array
    {
        $searchValue = $request->input('search.value');
        if (is_null($searchValue)) {
            $search = $request->input('search');
            if (is_array($search)) {
                $searchValue = $search['value'] ?? '';
            } else {
                $searchValue = $search ?? '';
            }
        }
        $searchValue = trim((string) $searchValue);

        $dateFilter = $request->input('date_filter');
        $warehouseFilterInput = $request->input('warehouse_filter');
        $productFilterInput = $request->input('product_filter');
        $userWarehouseId = auth()->user()->warehouse_id;

        $normalizedWarehouseFilter = null;
        if ($userWarehouseId) {
            $normalizedWarehouseFilter = $userWarehouseId;
        } elseif ($warehouseFilterInput && $warehouseFilterInput !== 'semua') {
            $normalizedWarehouseFilter = $warehouseFilterInput;
        }

        $query = StockMovement::query()
            ->from('stock_movements as sm')
            ->select([
                'sm.id as movement_id',
                'sm.date',
                'w.name as warehouse_name',
                'i.sku as sku_name',
                'i.nama_barang as item_name',
                'sm.description',
                'sm.stock_before',
                // qty in/out
                DB::raw("CASE WHEN sm.type = 'stock_in' THEN sm.quantity ELSE 0 END as stock_in"),
                DB::raw("CASE WHEN sm.type = 'stock_out' THEN -sm.quantity ELSE 0 END as stock_out"),
                'sm.stock_after',
                // koli metrics
                'sm.koli as movement_koli',
                DB::raw("CASE WHEN sm.type = 'stock_in' THEN sm.koli ELSE 0 END as stock_in_koli"),
                DB::raw("CASE WHEN sm.type = 'stock_out' THEN -sm.koli ELSE 0 END as stock_out_koli"),
                DB::raw("CASE WHEN COALESCE(i.koli,0) > 0 THEN sm.stock_before / i.koli ELSE 0 END as stock_before_koli"),
                DB::raw("CASE WHEN COALESCE(i.koli,0) > 0 THEN sm.stock_after / i.koli ELSE 0 END as stock_after_koli"),
                'i.koli as item_koli_per_unit',
                'u.name as user_name',
            ])
            ->leftJoin('items as i', 'sm.item_id', '=', 'i.id')
            ->leftJoin('warehouses as w', 'sm.warehouse_id', '=', 'w.id')
            ->leftJoin('users as u', 'sm.user_id', '=', 'u.id');

        if ($userWarehouseId) {
            $query->where('sm.warehouse_id', $userWarehouseId);
        }

        $recordsTotal = (clone $query)->count();

        if (!$userWarehouseId && $normalizedWarehouseFilter) {
            $query->where('sm.warehouse_id', $normalizedWarehouseFilter);
        }

        if ($searchValue !== '') {
            $query->where(function ($q) use ($searchValue) {
                $q->where('sm.description', 'LIKE', "%{$searchValue}%")
                    ->orWhere('w.name', 'LIKE', "%{$searchValue}%")
                    ->orWhere('i.sku', 'LIKE', "%{$searchValue}%")
                    ->orWhere('u.name', 'LIKE', "%{$searchValue}%");
            });
        }

        if ($productFilterInput && $productFilterInput !== 'semua') {
            $query->where('sm.item_id', (int) $productFilterInput);
        }

        if ($dateFilter) {
            if ($range = $this->parseDateRange($dateFilter)) {
                [$startDate, $endDate] = $range;
                $query->whereBetween('sm.date', [$startDate, $endDate]);
            }
        }

        $recordsFiltered = (clone $query)->count();

        return [$query, $recordsTotal, $recordsFiltered];
    }

    private function parseDateRange(?string $value): ?array
    {
        if (!$value) {
            return null;
        }

        $parts = preg_split('/\s+to\s+/i', trim($value));
        if (count($parts) !== 2) {
            return null;
        }

        try {
            $start = Carbon::parse($parts[0])->startOfDay();
            $end = Carbon::parse($parts[1])->endOfDay();
        } catch (\Throwable $th) {
            return null;
        }

        if ($start->gt($end)) {
            [$start, $end] = [$end, $start];
        }

        return [$start->toDateString(), $end->toDateString()];
    }
}
