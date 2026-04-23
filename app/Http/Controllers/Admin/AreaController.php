<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Item;
use App\Support\LocationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AreaController extends Controller
{
    public function index()
    {
        return view('admin.masterdata.areas.index');
    }

    public function data(Request $request)
    {
        $query = Area::orderBy('code');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $exact = $this->isExactSearch($request);
            $query->where(function ($q) use ($search, $exact) {
                $this->applyTextSearch($q, 'code', $search, $exact);
                $this->applyTextSearch($q, 'name', $search, $exact, 'or');
            });
        }

        $recordsTotal = Area::count();
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $data = $query->get()->map(function ($area) {
            return [
                'id' => $area->id,
                'code' => $area->code,
                'name' => $area->name,
                'is_active' => (bool) $area->is_active,
                'sort_order' => $area->sort_order,
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
            'code' => ['required', 'string', 'max:50', 'unique:areas,code'],
            'name' => ['required', 'string', 'max:150'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $validated['code'] = strtoupper(trim($validated['code']));
        $validated['is_active'] = array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : true;

        DB::beginTransaction();
        try {
            $area = Area::create($validated);
            DB::commit();

            return response()->json([
                'message' => 'Area berhasil dibuat',
                'area' => [
                    'id' => $area->id,
                    'code' => $area->code,
                    'name' => $area->name,
                ],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal membuat area',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, Area $area)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('areas', 'code')->ignore($area->id)],
            'name' => ['required', 'string', 'max:150'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $validated['code'] = strtoupper(trim($validated['code']));
        $validated['is_active'] = array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : true;

        DB::beginTransaction();
        try {
            $oldCode = $area->code;
            $area->update($validated);

            if ($oldCode !== $area->code) {
                $area->locations()->get()->each(function ($location) use ($area) {
                    $location->code = LocationService::buildAddress(
                        $area->code,
                        $location->rack_code,
                        (int) $location->column_no,
                        (int) $location->row_no
                    );
                    $location->save();
                });

                Item::with('location')
                    ->where('area_id', $area->id)
                    ->get()
                    ->each(function ($item) use ($area) {
                        $item->address = $item->location?->code ?? $area->code;
                        $item->save();
                    });
            }

            DB::commit();

            return response()->json([
                'message' => 'Area berhasil diperbarui',
                'area' => [
                    'id' => $area->id,
                    'code' => $area->code,
                    'name' => $area->name,
                ],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal memperbarui area',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Area $area)
    {
        DB::beginTransaction();
        try {
            $area->delete();
            DB::commit();
            return response()->json(['message' => 'Area berhasil dihapus']);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menghapus area',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
