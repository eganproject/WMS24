<?php

namespace Tests\Feature\Admin;

use App\Models\Item;
use App\Models\ItemStock;
use App\Models\OutboundItem;
use App\Models\OutboundTransaction;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\WarehouseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OutboundReturnKoliTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_return_accepts_matching_qty_and_koli(): void
    {
        $this->createWarehouseFixtures();
        $user = User::factory()->create();
        $supplier = Supplier::create(['name' => 'Supplier Retur']);
        $item = Item::create([
            'sku' => 'SKU-OUT-RET-001',
            'name' => 'Item Retur Koli',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
            'koli_qty' => 12,
        ]);
        ItemStock::create([
            'item_id' => $item->id,
            'warehouse_id' => WarehouseService::displayWarehouseId(),
            'stock' => 24,
        ]);

        $response = $this->actingAs($user)
            ->withoutMiddleware()
            ->postJson(route('admin.outbound.returns.store'), [
                'supplier_id' => $supplier->id,
                'ref_no' => 'RET-001',
                'warehouse_id' => WarehouseService::displayWarehouseId(),
                'transacted_at' => now()->format('Y-m-d H:i'),
                'items' => [
                    [
                        'item_id' => $item->id,
                        'qty' => 24,
                        'koli' => 2,
                        'note' => 'Retur per koli',
                    ],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Outbound berhasil disimpan dan menunggu approval.');

        $transaction = OutboundTransaction::with('items')->firstOrFail();
        $this->assertSame('return', $transaction->type);
        $this->assertSame(WarehouseService::displayWarehouseId(), (int) $transaction->warehouse_id);
        $this->assertSame($supplier->id, (int) $transaction->supplier_id);

        $this->assertCount(1, $transaction->items);
        $this->assertSame(24, (int) $transaction->items->first()->qty);
    }

    public function test_store_return_rejects_when_qty_and_koli_do_not_match(): void
    {
        $this->createWarehouseFixtures();
        $user = User::factory()->create();
        $supplier = Supplier::create(['name' => 'Supplier Retur']);
        $item = Item::create([
            'sku' => 'SKU-OUT-RET-002',
            'name' => 'Item Retur Mismatch',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
            'koli_qty' => 10,
        ]);

        $response = $this->actingAs($user)
            ->withoutMiddleware()
            ->postJson(route('admin.outbound.returns.store'), [
                'supplier_id' => $supplier->id,
                'warehouse_id' => WarehouseService::displayWarehouseId(),
                'transacted_at' => now()->format('Y-m-d H:i'),
                'items' => [
                    [
                        'item_id' => $item->id,
                        'qty' => 15,
                        'koli' => 2,
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.qty', 'items.0.koli']);
    }

    public function test_store_return_rejects_when_selected_warehouse_stock_is_short(): void
    {
        $this->createWarehouseFixtures();
        $user = User::factory()->create();
        $supplier = Supplier::create(['name' => 'Supplier Retur']);
        $item = Item::create([
            'sku' => 'SKU-OUT-RET-STOCK',
            'name' => 'Item Retur Stok Kurang',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
            'koli_qty' => 12,
        ]);
        ItemStock::create([
            'item_id' => $item->id,
            'warehouse_id' => WarehouseService::displayWarehouseId(),
            'stock' => 11,
        ]);

        $response = $this->actingAs($user)
            ->withoutMiddleware()
            ->postJson(route('admin.outbound.returns.store'), [
                'supplier_id' => $supplier->id,
                'warehouse_id' => WarehouseService::displayWarehouseId(),
                'transacted_at' => now()->format('Y-m-d H:i'),
                'items' => [
                    [
                        'item_id' => $item->id,
                        'qty' => 12,
                        'koli' => 1,
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['qty'])
            ->assertJsonPath('errors.qty.0', 'Stok tidak mencukupi untuk SKU SKU-OUT-RET-STOCK. Tersedia 11, dibutuhkan 12.');

        $this->assertDatabaseCount('outbound_transactions', 0);
    }

    public function test_show_return_includes_derived_koli_for_edit_form(): void
    {
        $this->createWarehouseFixtures();
        $user = User::factory()->create();
        $supplier = Supplier::create(['name' => 'Supplier Retur']);
        $item = Item::create([
            'sku' => 'SKU-OUT-RET-003',
            'name' => 'Item Retur Edit',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
            'koli_qty' => 6,
        ]);

        $transaction = OutboundTransaction::create([
            'code' => 'OUT-RET-TEST',
            'type' => 'return',
            'supplier_id' => $supplier->id,
            'warehouse_id' => WarehouseService::displayWarehouseId(),
            'transacted_at' => now(),
            'created_by' => $user->id,
            'status' => 'pending',
        ]);

        OutboundItem::create([
            'outbound_transaction_id' => $transaction->id,
            'item_id' => $item->id,
            'qty' => 18,
            'note' => 'Retur edit',
        ]);

        $response = $this->actingAs($user)
            ->withoutMiddleware()
            ->getJson(route('admin.outbound.returns.show', $transaction->id));

        $response->assertOk()
            ->assertJsonPath('items.0.item_id', $item->id)
            ->assertJsonPath('items.0.qty', 18)
            ->assertJsonPath('items.0.koli', 3);
    }

    private function createWarehouseFixtures(): array
    {
        $mainWarehouse = Warehouse::firstOrCreate(
            ['code' => 'GUDANG_BESAR'],
            ['name' => 'Gudang Besar', 'type' => 'main']
        );
        $displayWarehouse = Warehouse::firstOrCreate(
            ['code' => 'GUDANG_DISPLAY'],
            ['name' => 'Gudang Display', 'type' => 'display']
        );
        $damagedWarehouse = Warehouse::firstOrCreate(
            ['code' => 'GUDANG_RUSAK'],
            ['name' => 'Gudang Rusak', 'type' => 'damaged']
        );

        return [$mainWarehouse, $displayWarehouse, $damagedWarehouse];
    }
}
