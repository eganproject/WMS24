<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Lane;
use App\Support\LocationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LaneController extends Controller
{
    public function index()
    {
        return view('admin.masterdata.lanes.index');
    }

    public function data(Request $request)
    {
        $query = Lane::orderBy('code');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $recordsTotal = Lane::count();
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $data = $query->get()->map(function ($lane) {
            return [
                'id' => $lane->id,
                'code' => $lane->code,
                'name' => $lane->name,
                'is_active' => (bool) $lane->is_active,
                'sort_order' => $lane->sort_order,
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
            'code' => ['required', 'string', 'max:50', 'unique:lanes,code'],
            'name' => ['required', 'string', 'max:150'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $validated['code'] = strtoupper(trim($validated['code']));
        $validated['is_active'] = array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : true;

        DB::beginTransaction();
        try {
            $lane = Lane::create($validated);
            DB::commit();

            return response()->json([
                'message' => 'Lane berhasil dibuat',
                'lane' => [
                    'id' => $lane->id,
                    'code' => $lane->code,
                    'name' => $lane->name,
                ],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal membuat lane',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, Lane $lane)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('lanes', 'code')->ignore($lane->id)],
            'name' => ['required', 'string', 'max:150'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $validated['code'] = strtoupper(trim($validated['code']));
        $validated['is_active'] = array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : true;

        DB::beginTransaction();
        try {
            $oldCode = $lane->code;
            $lane->update($validated);

            if ($oldCode !== $lane->code) {
                $lane->locations()->get()->each(function ($location) use ($lane) {
                    $location->code = LocationService::buildAddress(
                        $lane->code,
                        $location->rack_code,
                        (int) $location->column_no,
                        (int) $location->row_no
                    );
                    $location->save();
                });

                Item::with('location')
                    ->where('lane_id', $lane->id)
                    ->get()
                    ->each(function ($item) use ($lane) {
                        $item->address = $item->location?->code ?? $lane->code;
                        $item->save();
                    });
            }

            DB::commit();

            return response()->json([
                'message' => 'Lane berhasil diperbarui',
                'lane' => [
                    'id' => $lane->id,
                    'code' => $lane->code,
                    'name' => $lane->name,
                ],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal memperbarui lane',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Lane $lane)
    {
        DB::beginTransaction();
        try {
            $lane->delete();
            DB::commit();
            return response()->json(['message' => 'Lane berhasil dihapus']);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menghapus lane',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
