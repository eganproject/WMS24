<?php

namespace Tests\Feature\Admin;

use App\Models\CustomerReturn;
use App\Models\Item;
use App\Models\Resi;
use App\Models\ResiDetail;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerReturnFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_lookup_resi_returns_expected_items_and_missing_skus(): void
    {
        $this->createWarehouseFixtures();

        $item = Item::create([
            'sku' => 'SKU-RET-001',
            'name' => 'Item Retur Match',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
        ]);
        $uploader = User::factory()->create();

        $resi = Resi::create([
            'id_pesanan' => 'ORD-RET-001',
            'no_resi' => 'RESI-RET-001',
            'tanggal_pesanan' => now()->toDateString(),
            'tanggal_upload' => now()->toDateString(),
            'uploader_id' => $uploader->id,
        ]);

        ResiDetail::create([
            'resi_id' => $resi->id,
            'sku' => $item->sku,
            'qty' => 2,
        ]);
        ResiDetail::create([
            'resi_id' => $resi->id,
            'sku' => 'SKU-BELUM-ADA',
            'qty' => 1,
        ]);

        $response = $this->withoutMiddleware()->getJson(route('admin.inventory.customer-returns.lookup', [
            'resi_no' => $resi->no_resi,
        ]));

        $response->assertOk()
            ->assertJsonPath('matched', true)
            ->assertJsonPath('resi.no_resi', 'RESI-RET-001')
            ->assertJsonPath('resi.order_ref', 'ORD-RET-001')
            ->assertJsonPath('items.0.item_id', $item->id)
            ->assertJsonPath('items.0.expected_qty', 2)
            ->assertJsonPath('items.0.received_qty', 0)
            ->assertJsonPath('missing_skus.0.sku', 'SKU-BELUM-ADA')
            ->assertJsonPath('missing_skus.0.expected_qty', 1);
    }

    public function test_store_rejects_when_good_and_damaged_qty_do_not_match_received_qty(): void
    {
        $this->createWarehouseFixtures();

        $item = Item::create([
            'sku' => 'SKU-RET-VAL',
            'name' => 'Item Retur Invalid',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
        ]);

        $response = $this->withoutMiddleware()->postJson(route('admin.inventory.customer-returns.store'), [
            'resi_no' => 'RESI-INVALID-001',
            'received_at' => now()->format('Y-m-d H:i'),
            'items' => [
                [
                    'item_id' => $item->id,
                    'expected_qty' => 1,
                    'received_qty' => 1,
                    'good_qty' => 0,
                    'damaged_qty' => 0,
                ],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.received_qty']);
    }

    public function test_store_allows_resi_match_with_physical_mismatch_and_manual_extra_sku(): void
    {
        $this->createWarehouseFixtures();

        $expectedItem = Item::create([
            'sku' => 'SKU-RET-EXP',
            'name' => 'Expected Item',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
        ]);
        $extraItem = Item::create([
            'sku' => 'SKU-RET-EXTRA',
            'name' => 'Extra Item',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
        ]);
        $uploader = User::factory()->create();

        $resi = Resi::create([
            'id_pesanan' => 'ORD-RET-MISMATCH',
            'no_resi' => 'RESI-RET-MISMATCH',
            'tanggal_pesanan' => now()->toDateString(),
            'tanggal_upload' => now()->toDateString(),
            'uploader_id' => $uploader->id,
        ]);

        ResiDetail::create([
            'resi_id' => $resi->id,
            'sku' => $expectedItem->sku,
            'qty' => 2,
        ]);

        $response = $this->withoutMiddleware()->postJson(route('admin.inventory.customer-returns.store'), [
            'resi_no' => $resi->no_resi,
            'resi_id' => $resi->id,
            'received_at' => now()->format('Y-m-d H:i'),
            'note' => 'Isi fisik tidak sama dengan expected resi',
            'items' => [
                [
                    'item_id' => $expectedItem->id,
                    'expected_qty' => 2,
                    'received_qty' => 1,
                    'good_qty' => 1,
                    'damaged_qty' => 0,
                    'note' => 'Satu unit expected tidak ditemukan',
                ],
                [
                    'item_id' => $extraItem->id,
                    'expected_qty' => 0,
                    'received_qty' => 1,
                    'good_qty' => 1,
                    'damaged_qty' => 0,
                    'note' => 'Ada SKU tambahan di paket',
                ],
            ],
        ]);

        $response->assertOk();

        $customerReturn = CustomerReturn::with('items')->firstOrFail();
        $this->assertSame($resi->id, $customerReturn->resi_id);
        $this->assertCount(2, $customerReturn->items);
        $this->assertDatabaseHas('customer_return_items', [
            'customer_return_id' => $customerReturn->id,
            'item_id' => $expectedItem->id,
            'expected_qty' => 2,
            'received_qty' => 1,
            'good_qty' => 1,
            'damaged_qty' => 0,
        ]);
        $this->assertDatabaseHas('customer_return_items', [
            'customer_return_id' => $customerReturn->id,
            'item_id' => $extraItem->id,
            'expected_qty' => 0,
            'received_qty' => 1,
            'good_qty' => 1,
            'damaged_qty' => 0,
        ]);
    }

    public function test_finalize_customer_return_posts_good_stock_and_creates_damaged_intake(): void
    {
        [$mainWarehouse, $displayWarehouse, $damagedWarehouse] = $this->createWarehouseFixtures();

        $item = Item::create([
            'sku' => 'SKU-RET-POST',
            'name' => 'Item Retur Final',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
        ]);
        $uploader = User::factory()->create();

        $resi = Resi::create([
            'id_pesanan' => 'ORD-RET-POST',
            'no_resi' => 'RESI-RET-POST',
            'tanggal_pesanan' => now()->toDateString(),
            'tanggal_upload' => now()->toDateString(),
            'uploader_id' => $uploader->id,
        ]);

        ResiDetail::create([
            'resi_id' => $resi->id,
            'sku' => $item->sku,
            'qty' => 3,
        ]);

        $storeResponse = $this->withoutMiddleware()->postJson(route('admin.inventory.customer-returns.store'), [
            'resi_no' => $resi->no_resi,
            'received_at' => now()->format('Y-m-d H:i'),
            'note' => 'Paket dibuka untuk inspeksi',
            'items' => [
                [
                    'item_id' => $item->id,
                    'expected_qty' => 3,
                    'received_qty' => 3,
                    'good_qty' => 2,
                    'damaged_qty' => 1,
                    'note' => 'Satu unit lecet',
                ],
            ],
        ]);

        $storeResponse->assertOk();

        $customerReturn = CustomerReturn::with('items')->firstOrFail();
        $this->assertSame(CustomerReturn::STATUS_INSPECTED, $customerReturn->status);
        $this->assertSame($resi->id, $customerReturn->resi_id);

        $finalizeResponse = $this->withoutMiddleware()->postJson(route('admin.inventory.customer-returns.finalize'), [
            'ids' => [$customerReturn->id],
        ]);

        $finalizeResponse->assertOk()
            ->assertJsonPath('message', '1 retur customer berhasil difinalisasi.');

        $customerReturn->refresh();
        $this->assertSame(CustomerReturn::STATUS_COMPLETED, $customerReturn->status);
        $this->assertNotNull($customerReturn->damaged_good_id);

        $this->assertDatabaseHas('item_stocks', [
            'item_id' => $item->id,
            'warehouse_id' => $displayWarehouse->id,
            'stock' => 2,
        ]);
        $this->assertDatabaseHas('item_stocks', [
            'item_id' => $item->id,
            'warehouse_id' => $damagedWarehouse->id,
            'stock' => 1,
        ]);
        $this->assertDatabaseMissing('item_stocks', [
            'item_id' => $item->id,
            'warehouse_id' => $mainWarehouse->id,
            'stock' => 3,
        ]);

        $this->assertDatabaseHas('stock_mutations', [
            'item_id' => $item->id,
            'warehouse_id' => $displayWarehouse->id,
            'direction' => 'in',
            'qty' => 2,
            'source_type' => 'customer_return',
            'source_subtype' => 'good',
            'source_id' => $customerReturn->id,
        ]);
        $this->assertDatabaseHas('damaged_goods', [
            'id' => $customerReturn->damaged_good_id,
            'source_type' => 'customer_return',
            'status' => 'approved',
        ]);
        $this->assertDatabaseHas('damaged_good_items', [
            'damaged_good_id' => $customerReturn->damaged_good_id,
            'item_id' => $item->id,
            'qty' => 1,
        ]);
        $this->assertDatabaseHas('stock_mutations', [
            'item_id' => $item->id,
            'warehouse_id' => $damagedWarehouse->id,
            'direction' => 'in',
            'qty' => 1,
            'source_type' => 'damaged',
            'source_subtype' => 'customer_return',
            'source_id' => $customerReturn->damaged_good_id,
        ]);
    }

    public function test_show_route_renders_document_style_detail_page(): void
    {
        $this->createWarehouseFixtures();

        $item = Item::create([
            'sku' => 'SKU-RET-DOC',
            'name' => 'Item Retur Dokumen',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
        ]);

        $customerReturn = CustomerReturn::create([
            'code' => 'CRT-TEST-DOC',
            'resi_no' => 'RESI-DOC-001',
            'order_ref' => 'ORD-DOC-001',
            'received_at' => now(),
            'inspected_at' => now(),
            'status' => CustomerReturn::STATUS_INSPECTED,
            'note' => 'Catatan dokumen retur',
        ]);

        $customerReturn->items()->create([
            'item_id' => $item->id,
            'expected_qty' => 2,
            'received_qty' => 1,
            'good_qty' => 1,
            'damaged_qty' => 0,
            'note' => 'Satu unit diterima',
        ]);

        $response = $this->withoutMiddleware()->get(route('admin.inventory.customer-returns.show', $customerReturn->id));

        $response->assertOk()
            ->assertSee('CRT-TEST-DOC')
            ->assertSee('Dokumen retur customer untuk inspeksi dan finalisasi stok.')
            ->assertSee('SKU-RET-DOC')
            ->assertSee('Catatan dokumen retur');
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
