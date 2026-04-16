<?php

namespace App\Support;

use App\Models\DamagedAllocationItem;
use App\Models\DamagedGoodItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DamagedStockService
{
    public static function approvedAllocationTotalsSubquery(?int $excludeAllocationId = null)
    {
        $query = DamagedAllocationItem::query()
            ->selectRaw('damaged_allocation_items.damaged_good_item_id, SUM(damaged_allocation_items.qty) as allocated_qty')
            ->join('damaged_allocations', 'damaged_allocations.id', '=', 'damaged_allocation_items.damaged_allocation_id')
            ->where('damaged_allocation_items.line_type', 'source')
            ->whereNotNull('damaged_allocation_items.damaged_good_item_id')
            ->where('damaged_allocations.status', 'approved');

        if ($excludeAllocationId && $excludeAllocationId > 0) {
            $query->where('damaged_allocation_items.damaged_allocation_id', '!=', $excludeAllocationId);
        }

        return $query->groupBy('damaged_allocation_items.damaged_good_item_id');
    }

    public static function remainingQtyMap(array $damagedGoodItemIds, ?int $excludeAllocationId = null): array
    {
        $ids = collect($damagedGoodItemIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if (empty($ids)) {
            return [];
        }

        $allocatedSub = self::approvedAllocationTotalsSubquery($excludeAllocationId);

        return DamagedGoodItem::query()
            ->leftJoinSub($allocatedSub, 'allocated', function ($join) {
                $join->on('allocated.damaged_good_item_id', '=', 'damaged_good_items.id');
            })
            ->whereIn('damaged_good_items.id', $ids)
            ->selectRaw('
                damaged_good_items.id,
                damaged_good_items.qty as received_qty,
                COALESCE(allocated.allocated_qty, 0) as allocated_qty,
                CASE
                    WHEN damaged_good_items.qty - COALESCE(allocated.allocated_qty, 0) < 0 THEN 0
                    ELSE damaged_good_items.qty - COALESCE(allocated.allocated_qty, 0)
                END as remaining_qty
            ')
            ->get()
            ->mapWithKeys(function ($row) {
                return [
                    (int) $row->id => [
                        'received_qty' => (int) $row->received_qty,
                        'allocated_qty' => (int) $row->allocated_qty,
                        'remaining_qty' => (int) $row->remaining_qty,
                    ],
                ];
            })
            ->all();
    }

    public static function availableSourceLines(?string $search = null, ?int $excludeAllocationId = null): Collection
    {
        $allocatedSub = self::approvedAllocationTotalsSubquery($excludeAllocationId);
        $query = DamagedGoodItem::query()
            ->join('damaged_goods', 'damaged_goods.id', '=', 'damaged_good_items.damaged_good_id')
            ->join('items', 'items.id', '=', 'damaged_good_items.item_id')
            ->leftJoin('warehouses as source_warehouses', 'source_warehouses.id', '=', 'damaged_goods.source_warehouse_id')
            ->leftJoinSub($allocatedSub, 'allocated', function ($join) {
                $join->on('allocated.damaged_good_item_id', '=', 'damaged_good_items.id');
            })
            ->where('damaged_goods.status', 'approved')
            ->selectRaw('
                damaged_good_items.id,
                damaged_good_items.damaged_good_id,
                damaged_good_items.item_id,
                damaged_good_items.qty as received_qty,
                COALESCE(allocated.allocated_qty, 0) as allocated_qty,
                CASE
                    WHEN damaged_good_items.qty - COALESCE(allocated.allocated_qty, 0) < 0 THEN 0
                    ELSE damaged_good_items.qty - COALESCE(allocated.allocated_qty, 0)
                END as remaining_qty,
                damaged_goods.code as damage_code,
                damaged_goods.transacted_at as damage_transacted_at,
                damaged_goods.source_type as damage_source_type,
                items.sku as item_sku,
                items.name as item_name,
                source_warehouses.name as source_warehouse_name
            ')
            ->whereRaw('(damaged_good_items.qty - COALESCE(allocated.allocated_qty, 0)) > 0')
            ->orderBy('damaged_goods.transacted_at')
            ->orderBy('damaged_good_items.id');

        $search = trim((string) $search);
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('damaged_goods.code', 'like', "%{$search}%")
                    ->orWhere('items.sku', 'like', "%{$search}%")
                    ->orWhere('items.name', 'like', "%{$search}%")
                    ->orWhere('source_warehouses.name', 'like', "%{$search}%");
            });
        }

        return $query->get()->map(function ($row) {
            return [
                'id' => (int) $row->id,
                'damaged_good_id' => (int) $row->damaged_good_id,
                'item_id' => (int) $row->item_id,
                'received_qty' => (int) $row->received_qty,
                'allocated_qty' => (int) $row->allocated_qty,
                'remaining_qty' => (int) $row->remaining_qty,
                'damage_code' => (string) $row->damage_code,
                'damage_transacted_at' => $row->damage_transacted_at,
                'damage_source_type' => (string) $row->damage_source_type,
                'item_sku' => (string) $row->item_sku,
                'item_name' => (string) $row->item_name,
                'source_warehouse_name' => (string) ($row->source_warehouse_name ?? '-'),
                'label' => sprintf(
                    '%s - %s | Intake %s | Sisa %d',
                    $row->item_sku,
                    $row->item_name,
                    $row->damage_code,
                    (int) $row->remaining_qty
                ),
            ];
        })->values();
    }
}
