<?php

namespace App\Support;

use App\Models\DamagedGood;
use App\Models\DamagedAllocationItem;
use App\Models\DamagedGoodItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DamagedStockService
{
    public const AGE_BUCKET_0_7 = '0_7';
    public const AGE_BUCKET_8_30 = '8_30';
    public const AGE_BUCKET_31_60 = '31_60';
    public const AGE_BUCKET_61_PLUS = '61_plus';

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

    public static function availableSourceLines(?string $search = null, ?int $excludeAllocationId = null, bool $exact = false): Collection
    {
        return self::remainingSourceLines($search, $excludeAllocationId, $exact)
            ->map(function (array $row) {
                $row['label'] = sprintf(
                    '%s - %s | Intake %s | Sisa %d',
                    $row['item_sku'],
                    $row['item_name'],
                    $row['damage_code'],
                    $row['remaining_qty']
                );

                return $row;
            })
            ->values();
    }

    public static function availableSkuBalances(?string $search = null, ?int $excludeAllocationId = null, bool $exact = false): Collection
    {
        return self::remainingSourceLines($search, $excludeAllocationId, $exact)
            ->groupBy('item_id')
            ->map(function (Collection $rows) {
                $first = $rows->sortBy([
                    ['damage_transacted_at', 'asc'],
                    ['id', 'asc'],
                ])->first();

                $remainingQty = (int) $rows->sum('remaining_qty');
                $receivedQty = (int) $rows->sum('received_qty');
                $allocatedQty = (int) $rows->sum('allocated_qty');
                $sourceCount = $rows->count();

                return [
                    'id' => (int) ($first['item_id'] ?? 0),
                    'item_id' => (int) ($first['item_id'] ?? 0),
                    'received_qty' => $receivedQty,
                    'allocated_qty' => $allocatedQty,
                    'remaining_qty' => $remainingQty,
                    'source_count' => $sourceCount,
                    'oldest_damage_code' => (string) ($first['damage_code'] ?? ''),
                    'damage_code' => (string) ($first['damage_code'] ?? ''),
                    'damage_transacted_at' => $first['damage_transacted_at'] ?? null,
                    'item_sku' => (string) ($first['item_sku'] ?? ''),
                    'item_name' => (string) ($first['item_name'] ?? ''),
                    'source_warehouse_name' => $sourceCount > 1
                        ? $sourceCount.' sumber'
                        : (string) ($first['source_warehouse_name'] ?? '-'),
                    'label' => sprintf(
                        '%s - %s | Total sisa %d | %d sumber',
                        (string) ($first['item_sku'] ?? ''),
                        (string) ($first['item_name'] ?? ''),
                        $remainingQty,
                        $sourceCount
                    ),
                ];
            })
            ->filter(fn (array $row) => (int) ($row['item_id'] ?? 0) > 0 && (int) ($row['remaining_qty'] ?? 0) > 0)
            ->sortBy('item_sku')
            ->values();
    }

    public static function remainingSourceLines(
        ?string $search = null,
        ?int $excludeAllocationId = null,
        bool $exact = false,
        ?string $reasonCode = null
    ): Collection {
        $query = self::remainingSourceLinesQuery($excludeAllocationId);

        $search = trim((string) $search);
        if ($search !== '') {
            $query->where(function ($q) use ($search, $exact) {
                if ($exact) {
                    $lowered = mb_strtolower($search);
                    $q->whereRaw('LOWER(damaged_goods.code) = ?', [$lowered])
                        ->orWhereRaw('LOWER(items.sku) = ?', [$lowered])
                        ->orWhereRaw('LOWER(items.name) = ?', [$lowered])
                        ->orWhereRaw('LOWER(source_warehouses.name) = ?', [$lowered])
                        ->orWhereRaw('LOWER(damaged_good_items.reason_code) = ?', [$lowered]);

                    return;
                }

                $q->where('damaged_goods.code', 'like', "%{$search}%")
                    ->orWhere('items.sku', 'like', "%{$search}%")
                    ->orWhere('items.name', 'like', "%{$search}%")
                    ->orWhere('source_warehouses.name', 'like', "%{$search}%")
                    ->orWhere('damaged_good_items.reason_code', 'like', "%{$search}%");
            });
        }

        $reasonCode = trim((string) $reasonCode);
        if ($reasonCode !== '') {
            $query->where('damaged_good_items.reason_code', $reasonCode);
        }

        $today = now()->startOfDay();

        return $query->get()->map(function ($row) use ($today) {
            $reasonCode = (string) ($row->reason_code ?: DamagedGoodItem::REASON_OTHER);
            $transactedAt = $row->damage_transacted_at ? Carbon::parse($row->damage_transacted_at) : null;
            $ageDays = $transactedAt ? $transactedAt->copy()->startOfDay()->diffInDays($today) : 0;

            return [
                'id' => (int) $row->id,
                'damaged_good_id' => (int) $row->damaged_good_id,
                'item_id' => (int) $row->item_id,
                'received_qty' => (int) $row->received_qty,
                'allocated_qty' => (int) $row->allocated_qty,
                'remaining_qty' => (int) $row->remaining_qty,
                'reason_code' => $reasonCode,
                'reason_label' => DamagedGoodItem::reasonLabel($reasonCode),
                'damage_code' => (string) $row->damage_code,
                'damage_transacted_at' => $transactedAt,
                'damage_source_type' => (string) $row->damage_source_type,
                'damage_source_label' => DamagedGood::sourceLabelFor((string) $row->damage_source_type),
                'item_sku' => (string) $row->item_sku,
                'item_name' => (string) $row->item_name,
                'source_warehouse_name' => (string) ($row->source_warehouse_name ?? '-'),
                'age_days' => $ageDays,
                'age_bucket' => self::ageBucket($ageDays),
                'age_bucket_label' => self::ageBucketLabels()[self::ageBucket($ageDays)],
            ];
        })->values();
    }

    public static function ageBucket(int $ageDays): string
    {
        return match (true) {
            $ageDays <= 7 => self::AGE_BUCKET_0_7,
            $ageDays <= 30 => self::AGE_BUCKET_8_30,
            $ageDays <= 60 => self::AGE_BUCKET_31_60,
            default => self::AGE_BUCKET_61_PLUS,
        };
    }

    public static function ageBucketLabels(): array
    {
        return [
            self::AGE_BUCKET_0_7 => '0-7 hari',
            self::AGE_BUCKET_8_30 => '8-30 hari',
            self::AGE_BUCKET_31_60 => '31-60 hari',
            self::AGE_BUCKET_61_PLUS => '>60 hari',
        ];
    }

    private static function remainingSourceLinesQuery(?int $excludeAllocationId = null)
    {
        $allocatedSub = self::approvedAllocationTotalsSubquery($excludeAllocationId);

        return DamagedGoodItem::query()
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
                damaged_good_items.reason_code,
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
    }
}
