<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Item;
use App\Models\Location;
use App\Support\LocationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LocationController extends Controller
{
    public function index()
    {
        $areas = Area::orderBy('code')->get(['id', 'code', 'name']);
        return view('admin.masterdata.locations.index', compact('areas'));
    }

    public function data(Request $request)
    {
        $query = Location::with('area')->orderBy('code');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('rack_code', 'like', "%{$search}%")
                    ->orWhereHas('area', function ($areaQ) use ($search) {
                        $areaQ->where('code', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
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
