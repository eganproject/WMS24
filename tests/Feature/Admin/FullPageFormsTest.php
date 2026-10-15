<?php

namespace Tests\Feature\Admin;

use App\Http\Middleware\AuthorizeMenuPermission;
use App\Models\InboundTransaction;
use App\Models\Item;
use App\Models\ItemBarcode;
use App\Models\ItemStock;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\InboundScanStatus;
use App\Support\ItemBarcodeResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FullPageFormsTest extends TestCase
{
    use RefreshDatabase;

    public function test_item_create_page_renders_full_form(): void
    {
        $this->actingAs(User::factory()->create())
            ->withoutMiddleware(AuthorizeMenuPermission::class)
            ->get(route('admin.masterdata.items.create'))
            ->assertOk()
            ->assertSee('id="item_form"', false)
            ->assertSee('const initialItem = null;', false)
            ->assertSee('Tambah Item')
            ->assertDontSee('modal_item_form', false);
    }

    public function test_item_edit_page_prefills_existing_item(): void
    {
        $item = Item::create([
            'sku' => 'SKU-EDIT-01',
            'name' => 'Produk Edit',
            'item_type' => Item::TYPE_SINGLE,
            'koli_qty' => 12,
            'safety_stock' => 3,
        ]);
        ItemBarcode::create([
            'item_id' => $item->id,
            'barcode_value' => '8990001112223',
            'normalized_barcode' => ItemBarcodeResolver::normalize('8990001112223'),
            'normalized_hash' => ItemBarcodeResolver::hash(ItemBarcodeResolver::normalize('8990001112223')),
            'source_name' => 'Supplier A',
        ]);

        $response = $this->actingAs(User::factory()->create())
            ->withoutMiddleware(AuthorizeMenuPermission::class)
            ->get(route('admin.masterdata.items.edit', $item));

        $response->assertOk();
        $response->assertSee('Edit Item');
        $response->assertSee('"sku":"SKU-EDIT-01"', false);
        $response->assertSee('"koli_qty":12', false);
        $response->assertSee('"barcode_value":"8990001112223"', false);
        $response->assertSee(route('admin.masterdata.items.update', ':id'), false);
    }

    public function test_item_detail_page_shows_master_data_and_stock(): void
    {
        $warehouse = Warehouse::firstOrCreate(['code' => 'GUDANG_BESAR'], [
            'name' => 'Gudang Besar',
            'type' => 'main',
        ]);
        $item = Item::create([
            'sku' => 'SKU-DETAIL-01',
            'name' => 'Produk Detail',
            'item_type' => Item::TYPE_SINGLE,
            'koli_qty' => 24,
        ]);
        ItemStock::create(['item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'stock' => 48]);

        $this->actingAs(User::factory()->create())
            ->withoutMiddleware(AuthorizeMenuPermission::class)
            ->get(route('admin.masterdata.items.show', $item))
            ->assertOk()
            ->assertSee('SKU-DETAIL-01')
            ->assertSee('Produk Detail')
            ->assertSee('Stok per Gudang')
            ->assertSee('Gudang Besar')
            ->assertSee('48');
    }

    public function test_items_list_links_to_full_pages_instead_of_modal(): void
    {
        $this->actingAs(User::factory()->create())
            ->withoutMiddleware(AuthorizeMenuPermission::class)
            ->get(route('admin.masterdata.items.index'))
            ->assertOk()
            ->assertDontSee('modal_item_form', false)
            ->assertSee("const showTpl   = '".route('admin.masterdata.items.show', ':id')."'", false)
            ->assertSee("const editTpl   = '".route('admin.masterdata.items.edit', ':id')."'", false);
    }

    public function test_receipt_create_page_renders_form_without_list(): void
    {
        $this->actingAs(User::factory()->create())
            ->withoutMiddleware(AuthorizeMenuPermission::class)
            ->get(route('admin.inbound.receipts.create'))
            ->assertOk()
            ->assertSee('id="stock_flow_form"', false)
            ->assertSee('const formPageMode = "create";', false)
            ->assertSee('Informasi Dokumen')
            ->assertDontSee('id="stock_flow_table"', false)
            ->assertDontSee('id="modal_stock_flow"', false);
    }

    public function test_receipt_edit_page_loads_pending_receipt(): void
    {
        $transaction = $this->receipt('RCV-EDIT-001', InboundScanStatus::PENDING_SCAN);

        $this->actingAs(User::factory()->create())
            ->withoutMiddleware(AuthorizeMenuPermission::class)
            ->get(route('admin.inbound.receipts.edit', $transaction->id))
            ->assertOk()
            ->assertSee('const formPageMode = "edit";', false)
            ->assertSee('const formPageEditId = '.$transaction->id.';', false)
            ->assertSee('RCV-EDIT-001');
    }

    public function test_receipt_edit_page_redirects_to_detail_once_scanning_started(): void
    {
        $transaction = $this->receipt('RCV-EDIT-002', InboundScanStatus::SCANNING);

        $this->actingAs(User::factory()->create())
            ->withoutMiddleware(AuthorizeMenuPermission::class)
            ->get(route('admin.inbound.receipts.edit', $transaction->id))
            ->assertRedirect(route('admin.inbound.receipts.detail', $transaction->id));
    }

    public function test_receipt_list_uses_page_links_while_other_inbound_lists_keep_modal(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withoutMiddleware(AuthorizeMenuPermission::class)
            ->get(route('admin.inbound.receipts.index'))
            ->assertOk()
            ->assertSee('id="stock_flow_table"', false)
            ->assertDontSee('id="modal_stock_flow"', false)
            ->assertSee('"edit_page":', false);

        $this->actingAs($user)
            ->withoutMiddleware(AuthorizeMenuPermission::class)
            ->get(route('admin.inbound.returns.index'))
            ->assertOk()
            ->assertSee('id="modal_stock_flow"', false)
            ->assertSee('const formPageMode = null;', false);
    }

    private function receipt(string $code, string $status): InboundTransaction
    {
        $warehouse = Warehouse::firstOrCreate(['code' => 'GUDANG_BESAR'], [
            'name' => 'Gudang Besar',
            'type' => 'main',
        ]);

        return InboundTransaction::create([
            'code' => $code,
            'type' => 'receipt',
            'warehouse_id' => $warehouse->id,
            'transacted_at' => now(),
            'status' => $status,
        ]);
    }
}
