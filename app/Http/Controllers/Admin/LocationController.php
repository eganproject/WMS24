<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Item;
use App\Models\Location;
use App\Models\Warehouse;
use App\Support\LocationService;
use App\Support\WarehouseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LocationController extends Controller
{
    public function index()
    {
        $areas = Area::with('warehouse:id,name')->orderBy('code')->get(['id', 'warehouse_id', 'code', 'name']);
        $warehouses = Warehouse::query()
            ->whereIn('id', [WarehouseService::displayWarehouseId(), WarehouseService::defaultWarehouseId()])
            ->orderBy('name')
            ->get(['id', 'name']);
        return view('admin.masterdata.locations.index', compact('areas', 'warehouses'));
    }

    public function data(Request $request)
    {
        $query = Location::with('area.warehouse:id,name')->orderBy('code');

        $warehouseFilter = (int) $request->input('warehouse_id', 0);
        if ($warehouseFilter > 0) {
            $includeUnassigned = $warehouseFilter === WarehouseService::displayWarehouseId();
            $query->whereHas('area', function ($areaQ) use ($warehouseFilter, $includeUnassigned) {
                $areaQ->where(function ($q) use ($warehouseFilter, $includeUnassigned) {
                    $q->where('warehouse_id', $warehouseFilter);
                    if ($includeUnassigned) {
                        $q->orWhereNull('warehouse_id');
                    }
                });
            });
        }

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $exact = $this->isExactSearch($request);
            $query->where(function ($q) use ($search, $exact) {
                $this->applyTextSearch($q, 'code', $search, $exact);
                $this->applyTextSearch($q, 'rack_code', $search, $exact, 'or');
                $q->orWhereHas('area', function ($areaQ) use ($search, $exact) {
                    $this->applyTextSearch($areaQ, 'code', $search, $exact);
                    $this->applyTextSearch($areaQ, 'name', $search, $exact, 'or');
                    });
            });
        }

        $recordsTotal = Location::count();
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $data = $query->get()->map(function ($loc) {
            return [
                'id' => $loc->id,
                'code' => $loc->code,
                'area_id' => $loc->area_id,
                'area_code' => $loc->area?->code ?? '-',
                'area_name' => $loc->area?->name ?? '-',
                'warehouse_name' => $loc->area?->warehouse?->name ?? 'Gudang Display',
                'rack_code' => $loc->rack_code,
                'column_no' => (int) $loc->column_no,
                'row_no' => (int) $loc->row_no,
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'area_id' => ['required', 'integer', 'exists:areas,id'],
            'rack_code' => ['required', 'string', 'max:20'],
            'column_no' => ['required', 'integer', 'min:1'],
            'row_no' => ['required', 'integer', 'min:1'],
        ]);

        $area = Area::find((int) $validated['area_id']);
        if (!$area) {
            throw ValidationException::withMessages([
                'area_id' => 'Area tidak ditemukan.',
            ]);
        }

        $rack = strtoupper(trim($validated['rack_code']));
        $col = (int) $validated['column_no'];
        $row = (int) $validated['row_no'];

        $exists = Location::where('area_id', $area->id)
            ->where('rack_code', $rack)
            ->where('column_no', $col)
            ->where('row_no', $row)
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages([
                'rack_code' => 'Lokasi sudah ada untuk area tersebut.',
            ]);
        }

        $code = LocationService::buildAddress($area->code, $rack, $col, $row);

        DB::beginTransaction();
        try {
            $location = Location::create([
                'area_id' => $area->id,
                'rack_code' => $rack,
                'column_no' => $col,
                'row_no' => $row,
                'code' => $code,
            ]);
            DB::commit();

            return response()->json([
                'message' => 'Lokasi berhasil dibuat',
                'location' => [
                    'id' => $location->id,
                    'code' => $location->code,
                ],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal membuat lokasi',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, Location $location)
    {
        $validated = $request->validate([
            'area_id' => ['required', 'integer', 'exists:areas,id'],
            'rack_code' => ['required', 'string', 'max:20'],
            'column_no' => ['required', 'integer', 'min:1'],
            'row_no' => ['required', 'integer', 'min:1'],
        ]);

        $area = Area::find((int) $validated['area_id']);
        if (!$area) {
            throw ValidationException::withMessages([
                'area_id' => 'Area tidak ditemukan.',
            ]);
        }

        $rack = strtoupper(trim($validated['rack_code']));
        $col = (int) $validated['column_no'];
        $row = (int) $validated['row_no'];

        $exists = Location::where('area_id', $area->id)
            ->where('rack_code', $rack)
            ->where('column_no', $col)
            ->where('row_no', $row)
            ->where('id', '!=', $location->id)
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages([
                'rack_code' => 'Lokasi sudah ada untuk area tersebut.',
            ]);
        }

        $location->loadMissing('area');
        if ($location->area && LocationService::areaWarehouseId($location->area) !== LocationService::areaWarehouseId($area)) {
            $inUse = Item::query()
                ->where('location_id', $location->id)
                ->orWhere('main_location_id', $location->id)
                ->exists();
            if ($inUse) {
                throw ValidationException::withMessages([
                    'area_id' => 'Lokasi sudah dipakai item, tidak bisa dipindah ke area gudang lain.',
                ]);
            }
        }

        $code = LocationService::buildAddress($area->code, $rack, $col, $row);

        DB::beginTransaction();
        try {
            $location->update([
                'area_id' => $area->id,
                'rack_code' => $rack,
                'column_no' => $col,
                'row_no' => $row,
                'code' => $code,
            ]);

            Item::where('location_id', $location->id)->update([
                'area_id' => $area->id,
                'address' => $location->code,
            ]);
            DB::commit();

            return response()->json([
                'message' => 'Lokasi berhasil diperbarui',
                'location' => [
                    'id' => $location->id,
                    'code' => $location->code,
                ],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal memperbarui lokasi',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Location $location)
    {
        DB::beginTransaction();
        try {
            $location->loadMissing('area');
            Item::where('location_id', $location->id)->update([
                'area_id' => $location->area_id,
                'address' => $location->area?->code ?? $location->code,
            ]);
            Item::where('main_location_id', $location->id)->update(['main_location_id' => null]);
            $location->delete();
            DB::commit();
            return response()->json(['message' => 'Lokasi berhasil dihapus']);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menghapus lokasi',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
