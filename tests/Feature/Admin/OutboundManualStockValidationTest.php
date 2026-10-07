<?php

namespace Tests\Feature\Admin;

use App\Models\Item;
use App\Models\ItemStock;
use App\Models\OutboundTransaction;
use App\Models\StockMutation;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\OutboundManualQcStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OutboundManualStockValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_outbound_rejects_when_selected_warehouse_stock_is_short(): void
    {
        $warehouse = $this->createWarehouse('GUDANG_TEST_MANUAL');
        $user = User::factory()->create();
        $item = $this->createItem('SKU-MANUAL-STOCK');

        ItemStock::create([
            'item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'stock' => 4,
        ]);

        $response = $this->actingAs($user)
            ->withoutMiddleware()
            ->postJson(route('admin.outbound.manuals.store'), [
                'warehouse_id' => $warehouse->id,
                'transacted_at' => now()->format('Y-m-d H:i'),
                'recipient_name' => 'Budi Penerima',
                'recipient_phone' => '08123456789',
                'recipient_address' => 'Jl. Manual No. 10',
                'items' => [
                    [
                        'item_id' => $item->id,
                        'qty' => 5,
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['qty'])
            ->assertJsonPath('errors.qty.0', 'Stok tidak mencukupi untuk SKU SKU-MANUAL-STOCK. Tersedia 4, dibutuhkan 5.');

        $this->assertDatabaseCount('outbound_transactions', 0);
    }

    public function test_manual_outbound_accepts_when_selected_warehouse_stock_is_enough(): void
    {
        $warehouse = $this->createWarehouse('GUDANG_TEST_MANUAL_OK');
        $user = User::factory()->create();
        $item = $this->createItem('SKU-MANUAL-OK');

        ItemStock::create([
            'item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'stock' => 5,
        ]);

        $this->actingAs($user)
            ->withoutMiddleware()
            ->postJson(route('admin.outbound.manuals.store'), [
                'warehouse_id' => $warehouse->id,
                'transacted_at' => now()->format('Y-m-d H:i'),
                'recipient_name' => 'Budi Penerima',
                'recipient_phone' => '08123456789',
                'recipient_address' => 'Jl. Manual No. 10',
                'items' => [
                    [
                        'item_id' => $item->id,
                        'qty' => 5,
                    ],
                ],
            ])
            ->assertOk();

        $transaction = OutboundTransaction::firstOrFail();
        $this->assertSame('manual', $transaction->type);
        $this->assertSame($warehouse->id, (int) $transaction->warehouse_id);
        $this->assertSame('Budi Penerima', $transaction->recipient_name);
        $this->assertSame('08123456789', $transaction->recipient_phone);
        $this->assertSame('Jl. Manual No. 10', $transaction->recipient_address);
        $this->assertSame(OutboundManualQcStatus::PENDING_QC, $transaction->status);
    }

    public function test_manual_outbound_can_be_deleted_before_qc_starts(): void
    {
        $warehouse = $this->createWarehouse('GUDANG_TEST_MANUAL_DELETE');
        $user = User::factory()->create();
        $item = $this->createItem('SKU-MANUAL-DELETE');

        ItemStock::create([
            'item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'stock' => 5,
        ]);

        $this->actingAs($user)
            ->withoutMiddleware()
            ->postJson(route('admin.outbound.manuals.store'), [
                'warehouse_id' => $warehouse->id,
                'transacted_at' => now()->format('Y-m-d H:i'),
                'recipient_name' => 'Penerima Hapus',
                'items' => [
                    [
                        'item_id' => $item->id,
                        'qty' => 2,
                    ],
                ],
            ])
            ->assertOk();

        $transaction = OutboundTransaction::firstOrFail();
        $this->assertSame(OutboundManualQcStatus::PENDING_QC, $transaction->status);

        $this->deleteJson(route('admin.outbound.manuals.destroy', $transaction->id))
            ->assertOk()
            ->assertJsonPath('message', 'Outbound berhasil dihapus');

        $this->assertDatabaseMissing('outbound_transactions', [
            'id' => $transaction->id,
        ]);
    }

    public function test_manual_outbound_cannot_be_deleted_after_qc_starts(): void
    {
        $warehouse = $this->createWarehouse('GUDANG_TEST_MANUAL_QC_LOCK');
        $user = User::factory()->create();
        $item = $this->createItem('SKU-MANUAL-QC-LOCK');

        ItemStock::create([
            'item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'stock' => 5,
        ]);

        $this->actingAs($user)
            ->withoutMiddleware()
            ->postJson(route('admin.outbound.manuals.store'), [
                'warehouse_id' => $warehouse->id,
                'transacted_at' => now()->format('Y-m-d H:i'),
                'recipient_name' => 'Penerima QC',
                'items' => [
                    [
                        'item_id' => $item->id,
                        'qty' => 2,
                    ],
                ],
            ])
            ->assertOk();

        $transaction = OutboundTransaction::firstOrFail();
        $transaction->update([
            'status' => OutboundManualQcStatus::QC_SCANNING,
        ]);

        $this->deleteJson(route('admin.outbound.manuals.destroy', $transaction->id))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Data sudah masuk tahap QC/selesai dan tidak bisa dihapus');

        $this->assertDatabaseHas('outbound_transactions', [
            'id' => $transaction->id,
            'status' => OutboundManualQcStatus::QC_SCANNING,
        ]);
    }

    public function test_manual_outbound_can_be_edited_before_qc_starts(): void
    {
        $warehouse = $this->createWarehouse('GUDANG_TEST_MANUAL_EDIT');
        $user = User::factory()->create();
        $item = $this->createItem('SKU-MANUAL-EDIT');

        ItemStock::create([
            'item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'stock' => 5,
        ]);

        $this->actingAs($user)
            ->withoutMiddleware()
            ->postJson(route('admin.outbound.manuals.store'), [
                'warehouse_id' => $warehouse->id,
                'transacted_at' => now()->format('Y-m-d H:i'),
                'recipient_name' => 'Penerima Awal',
                'items' => [
                    [
                        'item_id' => $item->id,
                        'qty' => 2,
                    ],
                ],
            ])
            ->assertOk();

        $transaction = OutboundTransaction::firstOrFail();

        $this->putJson(route('admin.outbound.manuals.update', $transaction->id), [
            'warehouse_id' => $warehouse->id,
            'transacted_at' => now()->format('Y-m-d H:i'),
            'recipient_name' => 'Penerima Diperbarui',
            'items' => [
                [
                    'item_id' => $item->id,
                    'qty' => 3,
                ],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Outbound berhasil diperbarui');

        $this->assertDatabaseHas('outbound_transactions', [
            'id' => $transaction->id,
            'status' => OutboundManualQcStatus::PENDING_QC,
            'recipient_name' => 'Penerima Diperbarui',
        ]);
        $this->assertDatabaseHas('outbound_items', [
            'outbound_transaction_id' => $transaction->id,
            'item_id' => $item->id,
            'qty' => 3,
        ]);
    }

    public function test_manual_outbound_cannot_be_edited_after_qc_starts(): void
    {
        $warehouse = $this->createWarehouse('GUDANG_TEST_MANUAL_EDIT_LOCK');
        $user = User::factory()->create();
        $item = $this->createItem('SKU-MANUAL-EDIT-LOCK');

        ItemStock::create([
            'item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'stock' => 5,
        ]);

        $this->actingAs($user)
            ->withoutMiddleware()
            ->postJson(route('admin.outbound.manuals.store'), [
                'warehouse_id' => $warehouse->id,
                'transacted_at' => now()->format('Y-m-d H:i'),
                'recipient_name' => 'Penerima QC',
                'items' => [
                    [
                        'item_id' => $item->id,
                        'qty' => 2,
                    ],
                ],
            ])
            ->assertOk();

        $transaction = OutboundTransaction::firstOrFail();
        $transaction->update([
            'status' => OutboundManualQcStatus::QC_SCANNING,
        ]);

        $this->putJson(route('admin.outbound.manuals.update', $transaction->id), [
            'warehouse_id' => $warehouse->id,
            'transacted_at' => now()->format('Y-m-d H:i'),
            'recipient_name' => 'Tidak Boleh Berubah',
            'items' => [
                [
                    'item_id' => $item->id,
                    'qty' => 3,
                ],
            ],
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Data sudah masuk tahap QC/selesai dan tidak bisa diubah');

        $this->assertDatabaseHas('outbound_transactions', [
            'id' => $transaction->id,
            'status' => OutboundManualQcStatus::QC_SCANNING,
            'recipient_name' => 'Penerima QC',
        ]);
    }

    public function test_admin_can_delete_scanning_manual_without_affecting_other_data(): void
    {
        $user = User::factory()->create(['email' => 'admin@gmail.com']);
        $warehouse = $this->createWarehouse('DELETE-SCANNING');
        $item = $this->createItem('DELETE-SCANNING');
        ItemStock::create(['item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'stock' => 10]);
        $transactions = [];
        foreach (['SCAN-DELETE', 'SCAN-KEEP'] as $code) {
            $tx = OutboundTransaction::create([
                'code' => $code, 'type' => 'manual', 'status' => OutboundManualQcStatus::QC_SCANNING,
                'warehouse_id' => $warehouse->id, 'transacted_at' => now(), 'created_by' => $user->id,
            ]);
            $tx->items()->create(['item_id' => $item->id, 'qty' => 3]);
            $session = $tx->qcSession()->create(['started_by' => $user->id, 'started_at' => now()]);
            $session->items()->create([
                'item_id' => $item->id, 'sku' => $item->sku, 'expected_qty' => 3, 'scanned_qty' => 1,
            ]);
            $transactions[] = $tx;
        }
        [$target, $other] = $transactions;
        $targetSessionId = $target->qcSession->id;
        $mutation = StockMutation::create([
            'item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'direction' => 'out',
            'qty' => 2, 'stock_before' => 12, 'stock_after' => 10,
            'source_type' => 'outbound', 'source_id' => $other->id, 'occurred_at' => now(),
        ]);

        $this->actingAs($user)->withoutMiddleware()
            ->getJson(route('admin.outbound.manuals.data'))
            ->assertOk()->assertJsonPath('data.0.can_delete', true);
        $this->deleteJson(route('admin.outbound.manuals.destroy', $target->id))->assertOk();

        $this->assertDatabaseMissing('outbound_transactions', ['id' => $target->id]);
        $this->assertDatabaseMissing('outbound_items', ['outbound_transaction_id' => $target->id]);
        $this->assertDatabaseMissing('outbound_qc_sessions', ['id' => $targetSessionId]);
        $this->assertDatabaseMissing('outbound_qc_session_items', ['outbound_qc_session_id' => $targetSessionId]);
        $this->assertDatabaseHas('outbound_transactions', ['id' => $other->id]);
        $this->assertDatabaseHas('outbound_items', ['outbound_transaction_id' => $other->id, 'qty' => 3]);
        $this->assertDatabaseHas('outbound_qc_session_items', ['outbound_qc_session_id' => $other->qcSession->id, 'scanned_qty' => 1]);
        $this->assertDatabaseHas('stock_mutations', ['id' => $mutation->id, 'qty' => 2]);
        $this->assertDatabaseHas('item_stocks', ['item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'stock' => 10]);
    }

    public function test_admin_cannot_delete_manual_after_qc_or_with_stock_mutations(): void
    {
        $user = User::factory()->create(['email' => 'admin@gmail.com']);
        $warehouse = $this->createWarehouse('DELETE-GUARD');
        $item = $this->createItem('DELETE-GUARD');
        foreach ([OutboundManualQcStatus::PENDING, OutboundManualQcStatus::APPROVED, OutboundManualQcStatus::QC_SCANNING] as $status) {
            $tx = OutboundTransaction::create([
                'code' => 'GUARD-'.$status, 'type' => 'manual', 'status' => $status,
                'warehouse_id' => $warehouse->id, 'transacted_at' => now(),
            ]);
            if ($status === OutboundManualQcStatus::QC_SCANNING) {
                StockMutation::create([
                    'item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'direction' => 'out',
                    'qty' => 1, 'stock_before' => 2, 'stock_after' => 1,
                    'source_type' => 'outbound', 'source_id' => $tx->id, 'occurred_at' => now(),
                ]);
            }
            $this->actingAs($user)->withoutMiddleware()
                ->deleteJson(route('admin.outbound.manuals.destroy', $tx->id))->assertStatus(422);
            $this->assertDatabaseHas('outbound_transactions', ['id' => $tx->id, 'status' => $status]);
        }
        $this->assertDatabaseCount('stock_mutations', 1);
    }

    private function createWarehouse(string $code): Warehouse
    {
        return Warehouse::create([
            'code' => $code,
            'name' => $code,
            'type' => 'display',
        ]);
    }

    private function createItem(string $sku): Item
    {
        return Item::create([
            'sku' => $sku,
            'name' => $sku,
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
        ]);
    }
}
