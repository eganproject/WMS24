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
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AreaController extends Controller
{
    public function index()
    {
        return view('admin.masterdata.areas.index', [
            'warehouses' => $this->binWarehouses(),
            'defaultAreaWarehouseId' => WarehouseService::displayWarehouseId(),
        ]);
    }

    /**
     * Gudang yang memakai bin location: gudang display dan gudang besar.
     */
    private function binWarehouses()
    {
        $ids = [WarehouseService::displayWarehouseId(), WarehouseService::defaultWarehouseId()];

        return Warehouse::query()
            ->whereIn('id', $ids)
            ->get(['id', 'code', 'name'])
            ->sortBy(fn ($warehouse) => array_search((int) $warehouse->id, $ids, true))
            ->values();
    }

    private function binWarehouseIds(): array
    {
        return [WarehouseService::displayWarehouseId(), WarehouseService::defaultWarehouseId()];
    }

    private function normalizeWarehouseId(array &$validated): void
    {
        $validated['warehouse_id'] = !empty($validated['warehouse_id'])
            ? (int) $validated['warehouse_id']
            : WarehouseService::displayWarehouseId();
    }

    public function data(Request $request)
    {
        $query = Area::with('warehouse:id,name')->orderBy('code');

        $warehouseFilter = (int) $request->input('warehouse_id', 0);
        if ($warehouseFilter > 0) {
            if ($warehouseFilter === WarehouseService::displayWarehouseId()) {
                $query->where(fn ($q) => $q->whereNull('warehouse_id')->orWhere('warehouse_id', $warehouseFilter));
            } else {
                $query->where('warehouse_id', $warehouseFilter);
            }
        }

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
                'warehouse_id' => LocationService::areaWarehouseId($area),
                'warehouse_name' => $area->warehouse?->name ?? 'Gudang Display',
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
            'warehouse_id' => ['nullable', 'integer', Rule::in($this->binWarehouseIds())],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $validated['code'] = strtoupper(trim($validated['code']));
        $validated['is_active'] = array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : true;
        $this->normalizeWarehouseId($validated);

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
            'warehouse_id' => ['nullable', 'integer', Rule::in($this->binWarehouseIds())],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $validated['code'] = strtoupper(trim($validated['code']));
        $validated['is_active'] = array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : true;
        if (array_key_exists('warehouse_id', $validated)) {
            $this->normalizeWarehouseId($validated);
            if ($validated['warehouse_id'] !== LocationService::areaWarehouseId($area) && $this->areaInUse($area)) {
                throw ValidationException::withMessages([
                    'warehouse_id' => 'Gudang area tidak bisa diubah karena area/lokasinya sudah dipakai item.',
                ]);
            }
        }

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

    private function areaInUse(Area $area): bool
    {
        $locationIds = Location::query()->where('area_id', $area->id)->pluck('id');

        return Item::query()
            ->where('area_id', $area->id)
            ->when($locationIds->isNotEmpty(), function ($q) use ($locationIds) {
                $q->orWhereIn('location_id', $locationIds)
                    ->orWhereIn('main_location_id', $locationIds)
                    ->orWhereIn('secondary_location_id', $locationIds)
                    ->orWhereIn('main_secondary_location_id', $locationIds);
            })
            ->exists();
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
