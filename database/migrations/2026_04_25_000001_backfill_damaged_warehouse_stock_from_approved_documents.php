<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (
            !Schema::hasTable('warehouses')
            || !Schema::hasTable('damaged_goods')
            || !Schema::hasTable('damaged_good_items')
            || !Schema::hasTable('damaged_allocations')
            || !Schema::hasTable('damaged_allocation_items')
            || !Schema::hasTable('item_stocks')
            || !Schema::hasTable('stock_mutations')
        ) {
            return;
        }

        $damagedWarehouseId = DB::table('warehouses')
            ->where('code', config('inventory.damaged_warehouse_code', 'GUDANG_RUSAK'))
            ->value('id');

        if (!$damagedWarehouseId) {
            return;
        }

        $approvedDamagedQty = DB::table('damaged_good_items')
            ->join('damaged_goods', 'damaged_goods.id', '=', 'damaged_good_items.damaged_good_id')
            ->where('damaged_goods.status', 'approved')
            ->groupBy('damaged_good_items.item_id')
            ->selectRaw('damaged_good_items.item_id, SUM(damaged_good_items.qty) as qty')
            ->pluck('qty', 'item_id');

        if ($approvedDamagedQty->isEmpty()) {
            return;
        }

        $approvedAllocatedQty = DB::table('damaged_allocation_items')
            ->join('damaged_allocations', 'damaged_allocations.id', '=', 'damaged_allocation_items.damaged_allocation_id')
            ->where('damaged_allocations.status', 'approved')
            ->where('damaged_allocation_items.line_type', 'source')
            ->groupBy('damaged_allocation_items.item_id')
            ->selectRaw('damaged_allocation_items.item_id, SUM(damaged_allocation_items.qty) as qty')
            ->pluck('qty', 'item_id');

        $now = now();
        $itemSkus = DB::table('items')
            ->whereIn('id', $approvedDamagedQty->keys()->all())
            ->pluck('sku', 'id');

        foreach ($approvedDamagedQty as $itemId => $receivedQty) {
            $expectedQty = max(0, (int) $receivedQty - (int) ($approvedAllocatedQty[$itemId] ?? 0));
            if ($expectedQty <= 0) {
                continue;
            }

            $stock = DB::table('item_stocks')
                ->where('item_id', $itemId)
                ->where('warehouse_id', $damagedWarehouseId)
                ->first();

            $currentQty = (int) ($stock->stock ?? 0);
            if ($currentQty >= $expectedQty) {
                continue;
            }

            $deltaQty = $expectedQty - $currentQty;
            if ($stock) {
                DB::table('item_stocks')
                    ->where('id', $stock->id)
                    ->update([
                        'stock' => $expectedQty,
                        'updated_at' => $now,
                    ]);
            } else {
                DB::table('item_stocks')->insert([
                    'item_id' => $itemId,
                    'warehouse_id' => $damagedWarehouseId,
                    'stock' => $expectedQty,
                    'safety_stock' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('stock_mutations')->insert([
                'item_id' => $itemId,
                'reference_item_id' => $itemId,
                'reference_sku' => $itemSkus[$itemId] ?? null,
                'warehouse_id' => $damagedWarehouseId,
                'direction' => 'in',
                'qty' => $deltaQty,
                'source_type' => 'damaged_reconcile',
                'source_subtype' => 'backfill',
                'source_id' => $damagedWarehouseId,
                'source_code' => 'DAMAGE-STOCK-BACKFILL',
                'note' => 'Backfill saldo Gudang Rusak dari dokumen barang rusak approved lama.',
                'occurred_at' => $now,
                'created_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Backfill stok bersifat koreksi data. Rollback otomatis berisiko mengurangi stok valid.
    }
};
