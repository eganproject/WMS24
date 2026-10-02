<?php

namespace Tests\Feature\Admin;

use App\Models\InboundItem;
use App\Models\InboundScanSession;
use App\Models\InboundScanSessionItem;
use App\Models\InboundTransaction;
use App\Models\Item;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\InboundScanStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InboundReturnListUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_return_page_uses_the_readable_item_card_and_wide_detail_modal(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->withoutMiddleware()
            ->get(route('admin.inbound.returns.index'));

        $response->assertOk();
        $response->assertSee('return-in-list-card', false);
        $response->assertSee('return-in-table', false);
        $response->assertSee('Ringkasan Item Retur');
        $response->assertSee('Dokumen Retur');
        $response->assertSee('Gudang &amp; Referensi', false);
        $response->assertSee('Rincian Item Retur');
        $response->assertSee('modal-xl', false);
        $response->assertSee('const enhancedItemList = true;', false);
        $response->assertSee('return-in-item-card__body', false);
        $response->assertSee('return-in-action-cell', false);
        $response->assertSee("{ data: 'id', visible: !enhancedItemList }", false);
        $response->assertSee("{ data: 'note', visible: !enhancedItemList", false);
        $response->assertDontSee('min-width: 1480px', false);
        $response->assertSee('Buka seluruh rincian item retur');
        $response->assertSee("typeof row?.can_delete === 'boolean'", false);
    }

    public function test_enhanced_list_is_scoped_to_inbound_returns(): void
    {
        $this->actingAs(User::factory()->create())
            ->withoutMiddleware()
            ->get(route('admin.inbound.manuals.index'))
            ->assertOk()
            ->assertSee('const enhancedItemList = false;', false)
            ->assertDontSee('<div class="card return-in-list-card">', false)
            ->assertDontSee('Ringkasan Item Retur');
    }

    public function test_return_data_provides_clear_item_identity_units_quantities_and_notes(): void
    {
        $warehouse = Warehouse::firstOrCreate(['code' => 'GUDANG_BESAR'], [
            'name' => 'Gudang Besar',
            'type' => 'main',
        ]);
        $firstItem = $this->item('SKU-RET-001', 'Produk Retur Pertama', 12);
        $secondItem = $this->item('SKU-RET-002', 'Produk Retur Kedua', 6);
        $transaction = InboundTransaction::create([
            'code' => 'RET-IN-UI-001',
            'type' => 'return',
            'warehouse_id' => $warehouse->id,
            'transacted_at' => now(),
            'status' => InboundScanStatus::PENDING_SCAN,
            'note' => 'Periksa kondisi kemasan',
        ]);
        InboundItem::create([
            'inbound_transaction_id' => $transaction->id,
            'item_id' => $firstItem->id,
            'input_unit' => 'koli',
            'koli' => 2,
            'qty' => 24,
            'note' => 'Dus penyok',
        ]);
        InboundItem::create([
            'inbound_transaction_id' => $transaction->id,
            'item_id' => $secondItem->id,
            'input_unit' => 'pcs',
            'koli' => 0,
            'qty' => 5,
        ]);

        $this->actingAs(User::factory()->create())
            ->withoutMiddleware()
            ->getJson(route('admin.inbound.returns.data', ['start' => 0, 'length' => 10]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(2, 'data.0.item_details')
            ->assertJsonPath('data.0.sku_count', 2)
            ->assertJsonPath('data.0.qty', 29)
            ->assertJsonPath('data.0.item_details.0.sku', 'SKU-RET-001')
            ->assertJsonPath('data.0.item_details.0.name', 'Produk Retur Pertama')
            ->assertJsonPath('data.0.item_details.0.input_unit', 'koli')
            ->assertJsonPath('data.0.item_details.0.koli', 2)
            ->assertJsonPath('data.0.item_details.0.qty', 24)
            ->assertJsonPath('data.0.item_details.0.note', 'Dus penyok')
            ->assertJsonPath('data.0.item_details.1.input_unit', 'pcs')
            ->assertJsonPath('data.0.item_details.1.qty', 5)
            ->assertJsonPath('data.0.can_delete', true);
    }

    public function test_scanning_return_with_zero_scanned_qty_can_be_deleted(): void
    {
        [$transaction, $session, $scanItem] = $this->scanningTransaction('return', 0);

        $this->withoutMiddleware()
            ->getJson(route('admin.inbound.returns.data', ['start' => 0, 'length' => 10]))
            ->assertOk()
            ->assertJsonPath('data.0.status', InboundScanStatus::SCANNING)
            ->assertJsonPath('data.0.scan_progress.scanned_qty', 0)
            ->assertJsonPath('data.0.can_delete', true);

        $this->withoutMiddleware()
            ->deleteJson(route('admin.inbound.returns.destroy', $transaction->id))
            ->assertOk()
            ->assertJsonPath('message', 'Inbound berhasil dihapus.');

        $this->assertDatabaseMissing('inbound_transactions', ['id' => $transaction->id]);
        $this->assertDatabaseMissing('inbound_scan_sessions', ['id' => $session->id]);
        $this->assertDatabaseMissing('inbound_scan_session_items', ['id' => $scanItem->id]);
    }

    public function test_scanning_return_with_scanned_qty_cannot_be_deleted(): void
    {
        [$transaction, $session, $scanItem] = $this->scanningTransaction('return', 1);

        $this->withoutMiddleware()
            ->getJson(route('admin.inbound.returns.data', ['start' => 0, 'length' => 10]))
            ->assertOk()
            ->assertJsonPath('data.0.scan_progress.scanned_qty', 1)
            ->assertJsonPath('data.0.can_delete', false);

        $this->withoutMiddleware()
            ->deleteJson(route('admin.inbound.returns.destroy', $transaction->id))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Retur inbound hanya dapat dihapus saat sedang scan apabila belum ada qty yang discan.');

        $this->assertDatabaseHas('inbound_transactions', ['id' => $transaction->id]);
        $this->assertDatabaseHas('inbound_scan_sessions', ['id' => $session->id]);
        $this->assertDatabaseHas('inbound_scan_session_items', ['id' => $scanItem->id, 'scanned_qty' => 1]);
    }

    public function test_completed_return_with_zero_scanned_qty_cannot_be_deleted(): void
    {
        [$transaction] = $this->scanningTransaction('return', 0);
        $transaction->update(['status' => InboundScanStatus::COMPLETED]);

        $this->withoutMiddleware()
            ->getJson(route('admin.inbound.returns.data', ['start' => 0, 'length' => 10]))
            ->assertOk()
            ->assertJsonPath('data.0.can_delete', false);

        $this->withoutMiddleware()
            ->deleteJson(route('admin.inbound.returns.destroy', $transaction->id))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Inbound yang sudah mulai discan tidak bisa dihapus.');

        $this->assertDatabaseHas('inbound_transactions', [
            'id' => $transaction->id,
            'status' => InboundScanStatus::COMPLETED,
        ]);
    }

    public function test_other_user_cannot_delete_receipt_even_with_zero_scan(): void
    {
        [$transaction, $session] = $this->scanningTransaction('receipt', 0);
        $this->actingAs(User::factory()->create(['email' => 'other@gmail.com']));

        $this->withoutMiddleware()
            ->getJson(route('admin.inbound.receipts.data', ['start' => 0, 'length' => 10]))
            ->assertOk()
            ->assertJsonPath('data.0.can_delete', false);

        $this->withoutMiddleware()
            ->deleteJson(route('admin.inbound.receipts.destroy', $transaction->id))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Inbound yang sudah mulai discan tidak bisa dihapus.');

        $this->assertDatabaseHas('inbound_transactions', ['id' => $transaction->id, 'type' => 'receipt']);
        $this->assertDatabaseHas('inbound_scan_sessions', ['id' => $session->id]);
    }

    public function test_admin_cannot_delete_partial_return_scan_and_data_remains_intact(): void
    {
        [$transaction, $session, $scanItem] = $this->scanningTransaction('return', 1);
        $stock = \App\Models\ItemStock::create([
            'item_id' => $scanItem->item_id, 'warehouse_id' => $transaction->warehouse_id, 'stock' => 20,
        ]);
        $this->actingAs(User::factory()->create(['email' => 'admin@gmail.com']))->withoutMiddleware();
        $this->getJson(route('admin.inbound.returns.data'))->assertJsonPath('data.0.can_delete', false);
        $this->deleteJson(route('admin.inbound.returns.destroy', $transaction->id))->assertUnprocessable();
        $this->assertDatabaseHas('inbound_transactions', ['id' => $transaction->id]);
        $this->assertDatabaseHas('inbound_items', ['inbound_transaction_id' => $transaction->id]);
        $this->assertDatabaseHas('inbound_scan_sessions', ['id' => $session->id]);
        $this->assertDatabaseHas('inbound_scan_session_items', ['id' => $scanItem->id]);
        $this->assertSame(20, (int) $stock->fresh()->stock);
        $this->assertDatabaseCount('stock_mutations', 0);
    }

    public function test_other_authenticated_user_cannot_delete_partial_return_scan(): void
    {
        [$transaction] = $this->scanningTransaction('return', 1);
        $this->actingAs(User::factory()->create(['email' => 'other@gmail.com']))->withoutMiddleware();
        $this->getJson(route('admin.inbound.returns.data'))->assertJsonPath('data.0.can_delete', false);
        $this->deleteJson(route('admin.inbound.returns.destroy', $transaction->id))->assertUnprocessable();
        $this->assertDatabaseHas('inbound_transactions', ['id' => $transaction->id]);
    }

    public function test_special_admin_cannot_delete_finalized_or_stock_mutated_return(): void
    {
        [$transaction, $session, $scanItem] = $this->scanningTransaction('return', 1);
        $this->actingAs(User::factory()->create(['email' => 'admin@gmail.com']))->withoutMiddleware();
        foreach (['completed', 'approved', 'session_completed', 'mutation'] as $case) {
            $transaction->update(['status' => $case === 'completed' ? InboundScanStatus::COMPLETED : InboundScanStatus::SCANNING,
                'approved_at' => $case === 'approved' ? now() : null]);
            $session->update(['completed_at' => $case === 'session_completed' ? now() : null]);
            if ($case === 'mutation') {
                \App\Models\StockMutation::create([
                    'item_id' => $scanItem->item_id, 'warehouse_id' => $transaction->warehouse_id,
                    'direction' => 'in', 'qty' => 1, 'stock_before' => 0, 'stock_after' => 1,
                    'source_type' => 'inbound', 'source_subtype' => 'return', 'source_id' => $transaction->id,
                    'occurred_at' => now(),
                ]);
            }
            $this->getJson(route('admin.inbound.returns.data'))->assertJsonPath('data.0.can_delete', false);
            $this->deleteJson(route('admin.inbound.returns.destroy', $transaction->id))->assertUnprocessable();
            $this->assertDatabaseHas('inbound_transactions', ['id' => $transaction->id]);
        }
    }

    public function test_admin_cannot_delete_scanning_receipt_and_stock_scan_and_qr_remain_intact(): void
    {
        [$transaction, $session, $scanItem] = $this->scanningTransaction('receipt', 1);
        $units = app(\App\Support\InboundKoliUnitService::class)->syncForTransaction($transaction);
        $stock = \App\Models\ItemStock::create([
            'item_id' => $scanItem->item_id, 'warehouse_id' => $transaction->warehouse_id, 'stock' => 20,
        ]);
        $this->actingAs(User::factory()->create(['email' => 'admin@gmail.com']))->withoutMiddleware();
        $this->get(route('admin.inbound.receipts.index'))->assertOk()->assertDontSee('seluruh progres scan');
        $this->getJson(route('admin.inbound.receipts.data'))->assertJsonPath('data.0.can_delete', false);
        $this->deleteJson(route('admin.inbound.receipts.destroy', $transaction->id))->assertUnprocessable();
        $this->assertDatabaseHas('inbound_transactions', ['id' => $transaction->id]);
        $this->assertDatabaseHas('inbound_items', ['inbound_transaction_id' => $transaction->id]);
        $this->assertDatabaseHas('inbound_scan_sessions', ['id' => $session->id]);
        $this->assertDatabaseHas('inbound_scan_session_items', ['id' => $scanItem->id]);
        $this->assertDatabaseHas('inbound_koli_units', ['id' => $units->first()->id]);
        $this->assertSame(20, (int) $stock->fresh()->stock);
        $this->assertDatabaseCount('stock_mutations', 0);
    }

    public function test_other_user_cannot_delete_receipt_with_scan_progress(): void
    {
        [$transaction, $session, $scanItem] = $this->scanningTransaction('receipt', 1);
        $this->actingAs(User::factory()->create(['email' => 'other@gmail.com']))->withoutMiddleware();
        $this->getJson(route('admin.inbound.receipts.data'))->assertJsonPath('data.0.can_delete', false);
        $this->deleteJson(route('admin.inbound.receipts.destroy', $transaction->id))->assertUnprocessable();
        $this->assertDatabaseHas('inbound_scan_session_items', ['id' => $scanItem->id, 'scanned_qty' => 1]);
    }

    public function test_admin_cannot_delete_receipt_with_finalization_stock_history_or_used_koli(): void
    {
        [$transaction, $session, $scanItem] = $this->scanningTransaction('receipt', 1);
        $units = app(\App\Support\InboundKoliUnitService::class)->syncForTransaction($transaction);
        $this->actingAs(User::factory()->create(['email' => 'admin@gmail.com']))->withoutMiddleware();
        foreach (['completed', 'approved', 'session_completed', 'used_koli', 'mutation'] as $case) {
            $transaction->update(['status' => $case === 'completed' ? InboundScanStatus::COMPLETED : InboundScanStatus::SCANNING,
                'approved_at' => $case === 'approved' ? now() : null]);
            $session->update(['completed_at' => $case === 'session_completed' ? now() : null]);
            $units->first()->update(['status' => $case === 'used_koli' ? 'reserved' : 'available']);
            if ($case === 'mutation') {
                \App\Models\StockMutation::create([
                    'item_id' => $scanItem->item_id, 'warehouse_id' => $transaction->warehouse_id,
                    'direction' => 'in', 'qty' => 1, 'stock_before' => 0, 'stock_after' => 1,
                    'source_type' => 'inbound', 'source_subtype' => 'receipt', 'source_id' => $transaction->id,
                    'occurred_at' => now(),
                ]);
            }
            $this->getJson(route('admin.inbound.receipts.data'))->assertJsonPath('data.0.can_delete', false);
            $this->deleteJson(route('admin.inbound.receipts.destroy', $transaction->id))->assertUnprocessable();
            $this->assertDatabaseHas('inbound_transactions', ['id' => $transaction->id]);
            $this->assertDatabaseHas('inbound_koli_units', ['id' => $units->first()->id]);
        }
    }

    public function test_admin_cannot_delete_manual_scan(): void
    {
        [$transaction] = $this->scanningTransaction('manual', 1);
        $this->actingAs(User::factory()->create(['email' => 'admin@gmail.com']))->withoutMiddleware();
        $this->getJson(route('admin.inbound.manuals.data'))->assertJsonPath('data.0.can_delete', false);
        $this->deleteJson(route('admin.inbound.manuals.destroy', $transaction->id))->assertUnprocessable();
        $this->assertDatabaseHas('inbound_transactions', ['id' => $transaction->id]);
    }

    private function scanningTransaction(string $type, int $scannedQty): array
    {
        $warehouse = Warehouse::firstOrCreate(['code' => 'GUDANG_BESAR'], [
            'name' => 'Gudang Besar',
            'type' => 'main',
        ]);
        $item = $this->item('SKU-SCAN-DELETE', 'Produk Scan Delete', 5);
        $transaction = InboundTransaction::create([
            'code' => strtoupper($type).'-SCAN-DELETE',
            'type' => $type,
            'warehouse_id' => $warehouse->id,
            'transacted_at' => now(),
            'status' => InboundScanStatus::SCANNING,
        ]);
        InboundItem::create([
            'inbound_transaction_id' => $transaction->id,
            'item_id' => $item->id,
            'input_unit' => 'koli',
            'koli' => 1,
            'qty' => 5,
        ]);
        $session = InboundScanSession::create([
            'inbound_transaction_id' => $transaction->id,
            'started_at' => now(),
        ]);
        $scanItem = InboundScanSessionItem::create([
            'inbound_scan_session_id' => $session->id,
            'item_id' => $item->id,
            'sku' => $item->sku,
            'item_name' => $item->name,
            'input_unit' => 'koli',
            'qty_per_koli' => 5,
            'expected_qty' => 5,
            'expected_koli' => 1,
            'scanned_qty' => $scannedQty,
            'scanned_koli' => $scannedQty > 0 ? 1 : 0,
        ]);

        return [$transaction, $session, $scanItem];
    }

    private function item(string $sku, string $name, int $koliQty): Item
    {
        return Item::create([
            'sku' => $sku,
            'name' => $name,
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
            'koli_qty' => $koliQty,
        ]);
    }
}
