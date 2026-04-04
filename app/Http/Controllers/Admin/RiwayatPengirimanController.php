<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RiwayatPengirimanController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $searchValue = $request->input('search.value', '');
            $start = (int) $request->input('start', 0);
            $length = (int) $request->input('length', 10);
            $draw = (int) $request->input('draw', 0);

            $statusFilter = $request->input('status');
            $refTypeFilter = $request->input('reference_type');
            $dateFilter = $request->input('date');

            $columns = [
                0 => 's.code',
                1 => 's.shipping_date',
                2 => 'reference_code',
                3 => 'from_warehouse_name',
                4 => 'to_warehouse_name',
                5 => 's.status',
                6 => 's.driver_name',
                7 => 's.id',
            ];

            $base = Shipment::query()->from('shipments as s')
                ->leftJoin('transfer_requests as tr', function ($j) {
                    $j->on('s.reference_id', '=', 'tr.id')
                        ->where('s.reference_type', Shipment::REFERENCE_TYPE_TRANSFER_REQUEST);
                })
                ->leftJoin('warehouses as fw', 'tr.from_warehouse_id', '=', 'fw.id')
                ->leftJoin('warehouses as tw', 'tr.to_warehouse_id', '=', 'tw.id')
                ->leftJoin('stock_in_orders as sio', function ($j) {
                    $j->on('s.reference_id', '=', 'sio.id')
                        ->where('s.reference_type', Shipment::REFERENCE_TYPE_STOCK_IN_ORDER);
                })
                ->leftJoin('warehouses as wio', 'sio.warehouse_id', '=', 'wio.id');

            $totalRecords = (clone $base)->count();

            if ($statusFilter && $statusFilter !== 'semua') {
                $base->where('s.status', $statusFilter);
            }
            if ($refTypeFilter && $refTypeFilter !== 'semua') {
                $base->where('s.reference_type', $refTypeFilter);
            }
            if ($dateFilter && $dateFilter !== 'semua') {
                if (str_contains($dateFilter, ' to ')) {
                    [$startDate, $endDate] = explode(' to ', $dateFilter);
                    $base->whereBetween('s.shipping_date', [$startDate, $endDate]);
                } else {
                    $base->whereDate('s.shipping_date', $dateFilter);
                }
            }

            if (!empty($searchValue)) {
                $base->where(function ($q) use ($searchValue) {
                    $q->where('s.code', 'LIKE', "%{$searchValue}%")
                        ->orWhere('tr.code', 'LIKE', "%{$searchValue}%")
                        ->orWhere('sio.code', 'LIKE', "%{$searchValue}%")
                        ->orWhere('fw.name', 'LIKE', "%{$searchValue}%")
                        ->orWhere('tw.name', 'LIKE', "%{$searchValue}%")
                        ->orWhere('wio.name', 'LIKE', "%{$searchValue}%")
                        ->orWhere('s.driver_name', 'LIKE', "%{$searchValue}%")
                        ->orWhere('s.license_plate', 'LIKE', "%{$searchValue}%");
                });
            }

            $totalFiltered = (clone $base)->count();

            $orderColumnIndex = (int) $request->input('order.0.column', 0);
            $orderColumnName = $columns[$orderColumnIndex] ?? $columns[0];
            $orderDirection = $request->input('order.0.dir', 'asc');

            $rows = $base->select([
                    's.id', 's.code', 's.shipping_date', 's.status', 's.reference_type', 's.reference_id',
                    DB::raw('COALESCE(tr.code, sio.code) as reference_code'),
                    DB::raw('fw.name as from_warehouse_name'),
                    DB::raw('COALESCE(tw.name, wio.name) as to_warehouse_name'),
                    's.vehicle_type', 's.license_plate', 's.driver_name'
                ])
                ->orderBy($orderColumnName, $orderDirection)
                ->offset($start)
                ->limit($length)
                ->get()
                ->map(function ($r) {
                    return [
                        'id' => $r->id,
                        'code' => $r->code,
                        'shipping_date' => $r->shipping_date ? Carbon::parse($r->shipping_date)->format('Y-m-d') : null,
                        'status' => $r->status,
                        'reference_type' => $r->reference_type,
                        'reference_code' => $r->reference_code,
                        'reference_id' => $r->reference_id,
                        'from_warehouse_name' => $r->from_warehouse_name,
                        'to_warehouse_name' => $r->to_warehouse_name,
                        'vehicle' => trim(($r->vehicle_type ? $r->vehicle_type.' ' : '') . ($r->license_plate ?? '')),
                        'driver' => $r->driver_name,
                    ];
                });

            return response()->json([
                'draw' => $draw,
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $totalFiltered,
                'data' => $rows,
            ]);
        }

        $warehouses = Warehouse::orderBy('name')->get();
        $statusOptions = ['dalam perjalanan', 'selesai'];
        return view('admin.riwayat_pengiriman.index', compact('warehouses', 'statusOptions'));
    }

    public function getStatusCounts(Request $request)
    {
        $refTypeFilter = $request->input('reference_type');
        $dateFilter = $request->input('date');

        $base = Shipment::query()->from('shipments as s');

        if ($refTypeFilter && $refTypeFilter !== 'semua') {
            $base->where('s.reference_type', $refTypeFilter);
        }
        if ($dateFilter && $dateFilter !== 'semua') {
            if (str_contains($dateFilter, ' to ')) {
                [$startDate, $endDate] = explode(' to ', $dateFilter);
                $base->whereBetween('s.shipping_date', [$startDate, $endDate]);
            } else {
                $base->whereDate('s.shipping_date', $dateFilter);
            }
        }

        return response()->json([
            'in_transit' => (clone $base)->where('s.status', 'dalam perjalanan')->count(),
            'completed' => (clone $base)->where('s.status', 'selesai')->count(),
        ]);
    }

    public function show(Shipment $shipment)
    {
        $shipment->load(['itemDetails.item']);
        $shipment->setRelation(
            'itemDetails',
            $shipment->itemDetails
                ->sortBy(function ($detail) {
                    $sku = optional($detail->item)->sku;
                    return strtoupper((string) ($sku ?? ''));
                }, SORT_NATURAL)
                ->values()
        );

        // Determine reference code and warehouses
        $referenceCode = null;
        $fromWarehouse = null;
        $toWarehouse = null;

        if ($shipment->reference_type === Shipment::REFERENCE_TYPE_TRANSFER_REQUEST) {
            $tr = DB::table('transfer_requests as tr')
                ->leftJoin('warehouses as fw', 'tr.from_warehouse_id', '=', 'fw.id')
                ->leftJoin('warehouses as tw', 'tr.to_warehouse_id', '=', 'tw.id')
                ->where('tr.id', $shipment->reference_id)
                ->select('tr.code as reference_code', 'fw.name as from_name', 'tw.name as to_name')
                ->first();
            $referenceCode = $tr->reference_code ?? null;
            $fromWarehouse = $tr->from_name ?? null;
            $toWarehouse = $tr->to_name ?? null;
        } else {
            $sio = DB::table('stock_in_orders as sio')
                ->leftJoin('warehouses as w', 'sio.warehouse_id', '=', 'w.id')
                ->where('sio.id', $shipment->reference_id)
                ->select('sio.code as reference_code', 'w.name as to_name')
                ->first();
            $referenceCode = $sio->reference_code ?? null;
            $toWarehouse = $sio->to_name ?? null;
        }

        // Load receipts for this shipment (if any)
        $receipts = \App\Models\GoodsReceipt::where('shipment_id', $shipment->id)
            ->with(['details.item', 'warehouse'])
            ->orderBy('receipt_date')
            ->get();

        return view('admin.riwayat_pengiriman.show', compact('shipment', 'referenceCode', 'fromWarehouse', 'toWarehouse', 'receipts'));
    }
}
