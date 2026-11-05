<?php

namespace Tests\Feature\Outbound;

use App\Models\Item;
use App\Models\ItemStock;
use App\Models\OutboundItem;
use App\Models\OutboundTransaction;
use App\Models\StockApiAllowedIp;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\OutboundManualQcStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OutboundManualReplenishmentTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $main;

    private Warehouse $display;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'outbound_manual_api.enabled' => true,
            'outbound_manual_api.token' => 'outbound-api-test-token',
            'outbound_manual_api.created_by_user_id' => null,
            'inventory.default_warehouse_code' => 'GUDANG_BESAR',
            'inventory.display_warehouse_code' => 'GUDANG_DISPLAY',
        ]);

        StockApiAllowedIp::create([
            'ip_address' => '127.0.0.1',
            'label' => 'PHPUnit',
            'is_active' => true,
        ]);

        $this->main = Warehouse::query()->updateOrCreate(['code' => 'GUDANG_BESAR'], ['name' => 'Gudang Besar', 'type' => 'main']);
        $this->display = Warehouse::query()->updateOrCreate(['code' => 'GUDANG_DISPLAY'], ['name' => 'Gudang Display', 'type' => 'display']);
    }

    public function test_display_shortfall_is_covered_by_main_warehouse_with_transfer_recommendation(): void
    {
        $item = $this->createItem('SKU-RPL-1', 12);
        $this->setStock($this->display, $item, 3);
        $this->setStock($this->main, $item, 24);

        $this->postJson('/api/v1/outbound/manuals', $this->payload('OMS-RPL-1', 10), $this->headers())
            ->assertCreated()
            ->assertJsonPath('data.status', OutboundManualQcStatus::PENDING_QC)
            ->assertJsonPath('data.warehouse.code', 'GUDANG_DISPLAY')
            ->assertJsonPath('data.replenishment.state', 'needs_transfer')
            ->assertJsonPath('data.replenishment.items.0.sku', 'SKU-RPL-1')
            ->assertJsonPath('data.replenishment.items.0.display_available_qty', 3)
            ->assertJsonPath('data.replenishment.items.0.shortfall_qty', 7)
            ->assertJsonPath('data.replenishment.items.0.recommended_koli', 1)
            ->assertJsonPath('data.replenishment.items.0.recommended_transfer_qty', 12);

        // Stok belum dipotong saat order dibuat.
        $this->assertDatabaseHas('item_stocks', ['item_id' => $item->id, 'warehouse_id' => $this->display->id, 'stock' => 3]);
        $this->assertDatabaseHas('item_stocks', ['item_id' => $item->id, 'warehouse_id' => $this->main->id, 'stock' => 24]);
    }

    public function test_order_fully_covered_by_display_has_ready_state(): void
    {
        $item = $this->createItem('SKU-RPL-1', 12);
        $this->setStock($this->display, $item, 10);

        $this->postJson('/api/v1/outbound/manuals', $this->payload('OMS-RPL-1', 4), $this->headers())
            ->assertCreated()
            ->assertJsonPath('data.replenishment.state', 'ready')
            ->assertJsonPath('data.replenishment.items', []);
    }

    public function test_it_rejects_when_display_plus_main_is_not_enough(): void
    {
        $item = $this->createItem('SKU-RPL-1', 12);
        $this->setStock($this->display, $item, 3);
        $this->setStock($this->main, $item, 12);

        $this->postJson('/api/v1/outbound/manuals', $this->payload('OMS-RPL-1', 20), $this->headers())
            ->assertStatus(422)
            ->assertJsonPath(
                'error.details.qty.0',
                'Stok tidak mencukupi untuk SKU SKU-RPL-1. Tersedia 15 (Display 3 + Gudang Besar 12), dibutuhkan 20.',
            );

        $this->assertDatabaseCount('outbound_transactions', 0);
    }

    public function test_it_rejects_when_main_warehouse_cannot_send_whole_koli(): void
    {
        $item = $this->createItem('SKU-RPL-1', 12);
        $this->setStock($this->display, $item, 3);
        $this->setStock($this->main, $item, 10);

        $this->postJson('/api/v1/outbound/manuals', $this->payload('OMS-RPL-1', 8), $this->headers())
            ->assertStatus(422)
            ->assertJsonPath(
                'error.details.qty.0',
                'Stok tidak mencukupi untuk SKU SKU-RPL-1. Display kurang 5 pcs, butuh transfer 1 koli (12 pcs) dari Gudang Besar, tetapi stok Gudang Besar yang tersedia 10 pcs.',
            );
    }

    public function test_open_manual_orders_reserve_stock_for_next_order(): void
    {
        $item = $this->createItem('SKU-RPL-1', 12);
        $this->setStock($this->display, $item, 5);
        $this->setStock($this->main, $item, 12);

        $this->postJson('/api/v1/outbound/manuals', $this->payload('OMS-RPL-1', 10), $this->headers())
            ->assertCreated();

        $this->postJson('/api/v1/outbound/manuals', $this->payload('OMS-RPL-2', 8), $this->headers())
            ->assertStatus(422)
            ->assertJsonPath(
                'error.details.qty.0',
                'Stok tidak mencukupi untuk SKU SKU-RPL-1. Tersedia 7 (Display 0 + Gudang Besar 7), dibutuhkan 8.',
            );

        $this->postJson('/api/v1/outbound/manuals', $this->payload('OMS-RPL-3', 7), $this->headers())
            ->assertStatus(422);
    }

    public function test_pending_transfer_to_display_changes_state_to_waiting(): void
    {
        $item = $this->createItem('SKU-RPL-1', 12);
        $this->setStock($this->display, $item, 3);
        $this->setStock($this->main, $item, 12);

        $transfer = StockTransfer::create([
            'code' => 'TRF-TEST-1',
            'from_warehouse_id' => $this->main->id,
            'to_warehouse_id' => $this->display->id,
            'transacted_at' => now(),
            'status' => 'qc_pending',
        ]);
        StockTransferItem::create([
            'stock_transfer_id' => $transfer->id,
            'item_id' => $item->id,
            'qty' => 12,
        ]);

        $this->postJson('/api/v1/outbound/manuals', $this->payload('OMS-RPL-1', 10), $this->headers())
            ->assertCreated()
            ->assertJsonPath('data.replenishment.state', 'waiting_transfer')
            ->assertJsonPath('data.replenishment.items.0.shortfall_qty', 0);
    }

    public function test_main_warehouse_order_keeps_using_main_stock_only(): void
    {
        $item = $this->createItem('SKU-RPL-1', 6);
        $this->setStock($this->display, $item, 100);
        $this->setStock($this->main, $item, 6);

        $payload = $this->payload('OMS-RPL-1', 12, 'GUDANG_BESAR');
        $payload['items'][0]['koli'] = 2;

        $this->postJson('/api/v1/outbound/manuals', $payload, $this->headers())
            ->assertStatus(422)
            ->assertJsonPath('error.details.qty.0', 'Stok tidak mencukupi untuk SKU SKU-RPL-1. Tersedia 6, dibutuhkan 12.');
    }

    public function test_qc_queue_shows_state_and_complete_is_blocked_until_display_has_stock(): void
    {
        $user = User::factory()->create();
        $item = $this->createItem('SKU-RPL-1', 12);
        $this->setStock($this->display, $item, 3);
        $this->setStock($this->main, $item, 24);

        $transaction = OutboundTransaction::create([
            'code' => 'OUT-MNL-TEST-1',
            'type' => 'manual',
            'warehouse_id' => $this->display->id,
            'transacted_at' => now(),
            'status' => OutboundManualQcStatus::PENDING_QC,
        ]);
        OutboundItem::create([
            'outbound_transaction_id' => $transaction->id,
            'item_id' => $item->id,
            'qty' => 10,
        ]);

        $this->withoutMiddleware();

        $this->actingAs($user)
            ->getJson(route('admin.outbound.manual-qc.transactions'))
            ->assertOk()
            ->assertJsonPath('transactions.0.replenishment_state', 'needs_transfer');

        $open = $this->actingAs($user)
            ->postJson(route('admin.outbound.manual-qc.open'), ['transaction_id' => $transaction->id])
            ->assertOk()
            ->assertJsonPath('transaction.replenishment.state', 'needs_transfer')
            ->assertJsonPath('transaction.replenishment.items.0.recommended_koli', 1);

        $transferUrl = (string) $open->json('transaction.replenishment.transfer_url');
        $this->assertStringContainsString('items='.$item->id.'%3A1', $transferUrl);
        $this->assertStringContainsString('from='.$this->main->id, $transferUrl);
        $this->assertStringContainsString('to='.$this->display->id, $transferUrl);

        $sessionId = $open->json('transaction.session.id');
        $this->actingAs($user)
            ->postJson(route('admin.outbound.manual-qc.scan-sku'), ['session_id' => $sessionId, 'code' => 'SKU-RPL-1', 'qty' => 10])
            ->assertOk();

        $this->actingAs($user)
            ->postJson(route('admin.outbound.manual-qc.complete'), ['session_id' => $sessionId])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Stok Gudang Display belum cukup: SKU-RPL-1 (tersedia 3, dibutuhkan 10). Selesaikan transfer dari Gudang Besar terlebih dahulu.');

        ItemStock::query()->where('item_id', $item->id)->where('warehouse_id', $this->display->id)->update(['stock' => 15]);

        $this->actingAs($user)
            ->postJson(route('admin.outbound.manual-qc.complete'), ['session_id' => $sessionId])
            ->assertOk();
    }

    private function payload(string $externalId, int $qty, string $warehouseCode = 'GUDANG_DISPLAY'): array
    {
        return [
            'external_id' => $externalId,
            'warehouse_code' => $warehouseCode,
            'transacted_at' => '2026-11-05T09:30:00+07:00',
            'ref_no' => 'ORDER-'.$externalId,
            'items' => [
                ['sku' => 'SKU-RPL-1', 'qty' => $qty],
            ],
        ];
    }

    private function headers(): array
    {
        return ['Authorization' => 'Bearer outbound-api-test-token'];
    }

    private function createItem(string $sku, int $koliQty = 0): Item
    {
        return Item::create([
            'sku' => $sku,
            'name' => $sku,
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
            'koli_qty' => $koliQty,
        ]);
    }

    private function setStock(Warehouse $warehouse, Item $item, int $qty): void
    {
        ItemStock::create([
            'warehouse_id' => $warehouse->id,
            'item_id' => $item->id,
            'stock' => $qty,
        ]);
    }
}
