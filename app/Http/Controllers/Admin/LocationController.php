<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Lane;
use App\Models\Location;
use App\Support\LocationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LocationController extends Controller
{
    public function index()
    {
        $lanes = Lane::orderBy('code')->get(['id', 'code', 'name']);
        return view('admin.masterdata.locations.index', compact('lanes'));
    }

    public function data(Request $request)
    {
        $query = Location::with('lane')->orderBy('code');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('rack_code', 'like', "%{$search}%")
                    ->orWhereHas('lane', function ($laneQ) use ($search) {
                        $laneQ->where('code', 'like', "%{$search}%")
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
                'lane_id' => $loc->lane_id,
                'lane_code' => $loc->lane?->code ?? '-',
                'lane_name' => $loc->lane?->name ?? '-',
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
            'lane_id' => ['required', 'integer', 'exists:lanes,id'],
            'rack_code' => ['required', 'string', 'max:20'],
            'column_no' => ['required', 'integer', 'min:1'],
            'row_no' => ['required', 'integer', 'min:1'],
        ]);

        $lane = Lane::find((int) $validated['lane_id']);
        if (!$lane) {
            throw ValidationException::withMessages([
                'lane_id' => 'Lane tidak ditemukan.',
            ]);
        }

        $rack = strtoupper(trim($validated['rack_code']));
        $col = (int) $validated['column_no'];
        $row = (int) $validated['row_no'];

        $exists = Location::where('lane_id', $lane->id)
            ->where('rack_code', $rack)
            ->where('column_no', $col)
            ->where('row_no', $row)
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages([
                'rack_code' => 'Lokasi sudah ada untuk lane tersebut.',
            ]);
        }

        $code = LocationService::buildAddress($lane->code, $rack, $col, $row);

        DB::beginTransaction();
        try {
            $location = Location::create([
                'lane_id' => $lane->id,
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
            'lane_id' => ['required', 'integer', 'exists:lanes,id'],
            'rack_code' => ['required', 'string', 'max:20'],
            'column_no' => ['required', 'integer', 'min:1'],
            'row_no' => ['required', 'integer', 'min:1'],
        ]);

        $lane = Lane::find((int) $validated['lane_id']);
        if (!$lane) {
            throw ValidationException::withMessages([
                'lane_id' => 'Lane tidak ditemukan.',
            ]);
        }

        $rack = strtoupper(trim($validated['rack_code']));
        $col = (int) $validated['column_no'];
        $row = (int) $validated['row_no'];

        $exists = Location::where('lane_id', $lane->id)
            ->where('rack_code', $rack)
            ->where('column_no', $col)
            ->where('row_no', $row)
            ->where('id', '!=', $location->id)
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages([
                'rack_code' => 'Lokasi sudah ada untuk lane tersebut.',
            ]);
        }

        $code = LocationService::buildAddress($lane->code, $rack, $col, $row);

        DB::beginTransaction();
        try {
            $location->update([
                'lane_id' => $lane->id,
                'rack_code' => $rack,
                'column_no' => $col,
                'row_no' => $row,
                'code' => $code,
            ]);

            Item::where('location_id', $location->id)->update([
                'lane_id' => $lane->id,
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
            $location->loadMissing('lane');
            Item::where('location_id', $location->id)->update([
                'lane_id' => $location->lane_id,
                'address' => $location->lane?->code ?? $location->code,
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
