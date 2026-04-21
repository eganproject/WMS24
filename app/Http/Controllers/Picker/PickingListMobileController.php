<?php

namespace App\Http\Controllers\Picker;

use App\Http\Controllers\Controller;
use App\Models\Lane;
use App\Models\QcScanException;
use App\Models\PickingList;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PickingListMobileController extends Controller
{
    public function index()
    {
        $authUser = auth()->user();
        $laneQuery = Lane::query()
            ->where('is_active', true)
            ->orderBy('code');

        if ($authUser?->lane_id) {
            $laneQuery->whereKey((int) $authUser->lane_id);
        }

        return view('picker.picking-list', [
            'routes' => [
                'dashboard' => route('picker.dashboard'),
                'data' => route('picker.picking-list.data'),
                'logout' => route('logout'),
            ],
            'lanes' => $laneQuery->get(['id', 'code', 'name']),
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
            ->with('item', 'item.location.lane', 'item.lane')
            ->whereDate('list_date', $date)
            ->orderBy('sku');
        $this->applyPackerExceptionFilter($query);

        $authUser = $request->user();
        $userLaneId = $authUser?->lane_id ? (int) $authUser->lane_id : null;
        if ($userLaneId) {
            $query->whereHas('item', function ($itemQ) use ($userLaneId) {
                $itemQ->where('lane_id', $userLaneId);
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

        $laneId = $userLaneId ?: $request->integer('lane_id');
        if ($laneId) {
            $query->whereHas('item', function ($itemQ) use ($laneId) {
                $itemQ->where('lane_id', (int) $laneId);
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
