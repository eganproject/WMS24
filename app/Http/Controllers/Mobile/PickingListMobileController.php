<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\QcScanException;
use App\Models\PickingList;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PickingListMobileController extends Controller
{
    public function index()
    {
        $authUser = auth()->user();
        $areaQuery = Area::query()
            ->where('is_active', true)
            ->orderBy('code');

        if ($authUser?->area_id) {
            $areaQuery->whereKey((int) $authUser->area_id);
        }

        return view('mobile.picking-list', [
            'routes' => [
                'dashboard' => route('mobile.dashboard'),
                'data' => route('mobile.picking-list.data'),
                'logout' => route('logout'),
            ],
            'areas' => $areaQuery->get(['id', 'code', 'name']),
            'today' => now()->toDateString(),
        ]);
    }

    public function data(Request $request)
    {
        $date = $request->input('date') ?: now()->toDateString();
        try {
            $date = Carbon::parse($date)->toDateString();
        } catch (\Throwable) {
            $date = now()->toDateString();
        }

        $query = PickingList::query()
            ->with('item', 'item.location.area', 'item.area')
            ->whereDate('list_date', $date)
            ->orderBy('sku');
        $this->applyPackerExceptionFilter($query);

        $authUser = $request->user();
        $userAreaId = $authUser?->area_id ? (int) $authUser->area_id : null;
        if ($userAreaId) {
            $query->whereHas('item', function ($itemQ) use ($userAreaId) {
                $itemQ->where('area_id', $userAreaId);
            });
        }

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('sku', 'like', "%{$search}%")
                    ->orWhereHas('item', function ($itemQ) use ($search) {
                        $itemQ->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $areaId = $userAreaId ?: $request->integer('area_id');
        if ($areaId) {
            $query->whereHas('item', function ($itemQ) use ($areaId) {
                $itemQ->where('area_id', (int) $areaId);
            });
        }

        $page = max(1, (int) $request->input('page', 1));
        $perPage = (int) $request->input('per_page', 5);
        $perPage = min(100, max(5, $perPage));
        $total = (clone $query)->count();
        $totalPages = (int) ceil($total / $perPage);
        if ($totalPages > 0 && $page > $totalPages) {
            $page = $totalPages;
        }

        $items = $query
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get()
            ->map(function ($row) {
            $item = $row->item;
            return [
                'item_id' => $item?->id,
                'sku' => $row->sku ?? '-',
                'name' => $item?->name ?? '-',
                'address' => $item?->resolvedAddress() ?? '',
                'qty' => (int) $row->qty,
                'remaining_qty' => (int) $row->remaining_qty,
            ];
        })->values();

        return response()->json([
            'date' => $date,
            'items' => $items,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
        ]);
    }

    private function applyPackerExceptionFilter($query): void
    {
        $query->whereNotIn('sku', QcScanException::query()->select('sku'));
    }
}
