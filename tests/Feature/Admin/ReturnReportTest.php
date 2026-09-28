<?php

namespace Tests\Feature\Admin;

use App\Models\CustomerReturn;
use App\Models\CustomerReturnItem;
use App\Models\DamagedGood;
use App\Models\InboundItem;
use App\Models\InboundScanSession;
use App\Models\InboundScanSessionItem;
use App\Models\InboundTransaction;
use App\Models\Item;
use App\Models\OutboundItem;
use App\Models\OutboundTransaction;
use App\Models\Resi;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ReturnReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_return_report_combines_customer_and_outbound_rows_with_correct_summary(): void
    {
        $displayWarehouse = Warehouse::firstOrCreate([
            'code' => 'GUDANG_DISPLAY',
        ], [
            'name' => 'Gudang Display',
            'type' => 'display',
        ]);

        $creator = User::factory()->create(['name' => 'Input Retur']);
        $inspector = User::factory()->create(['name' => 'Inspector Retur']);
        $finalizer = User::factory()->create(['name' => 'Finalizer Retur']);
        $supplier = Supplier::create(['name' => 'Supplier Retur']);

        $customerItem = Item::create([
            'sku' => 'SKU-RET-CUST',
            'name' => 'Item Retur Customer',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
        ]);
        $outboundItem = Item::create([
            'sku' => 'SKU-RET-OUT',
            'name' => 'Item Retur Outbound',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
        ]);

        $damagedGood = DamagedGood::create([
            'code' => 'DMG-RET-001',
            'source_type' => 'customer_return',
            'source_ref' => 'CRT-001',
            'transacted_at' => now()->subDay(),
            'status' => 'approved',
            'created_by' => $creator->id,
            'approved_by' => $finalizer->id,
            'approved_at' => now()->subDay(),
        ]);

        $resi = Resi::create([
            'id_pesanan' => 'ORD-001',
            'no_resi' => 'RESI-001',
            'tanggal_pesanan' => now()->subDays(3)->toDateString(),
            'tanggal_upload' => now()->subDays(2)->toDateString(),
            'uploader_id' => $creator->id,
        ]);

        $customerReturnMatched = CustomerReturn::create([
            'code' => 'CRT-001',
            'resi_id' => $resi->id,
            'damaged_good_id' => $damagedGood->id,
            'resi_no' => 'RESI-001',
            'order_ref' => 'ORD-001',
            'received_at' => now()->subDay(),
            'inspected_at' => now()->subDay(),
            'finalized_at' => now()->subDay(),
            'status' => CustomerReturn::STATUS_COMPLETED,
            'note' => 'Retur customer selesai',
            'created_by' => $creator->id,
            'inspected_by' => $inspector->id,
            'finalized_by' => $finalizer->id,
        ]);

        CustomerReturnItem::create([
            'customer_return_id' => $customerReturnMatched->id,
            'item_id' => $customerItem->id,
            'expected_qty' => 5,
            'received_qty' => 4,
            'good_qty' => 3,
            'damaged_qty' => 1,
            'note' => 'Satu pcs rusak',
        ]);

        $customerReturnUnmatched = CustomerReturn::create([
            'code' => 'CRT-002',
            'resi_no' => 'RESI-MANUAL',
            'order_ref' => null,
            'received_at' => now()->subHours(12),
            'inspected_at' => now()->subHours(12),
            'status' => CustomerReturn::STATUS_INSPECTED,
            'note' => 'Retur manual belum final',
            'created_by' => $creator->id,
            'inspected_by' => $inspector->id,
        ]);

        CustomerReturnItem::create([
            'customer_return_id' => $customerReturnUnmatched->id,
            'item_id' => $customerItem->id,
            'expected_qty' => 0,
            'received_qty' => 2,
            'good_qty' => 2,
            'damaged_qty' => 0,
            'note' => 'Input manual',
        ]);

        $outboundReturn = OutboundTransaction::create([
            'code' => 'OUT-RET-001',
            'type' => 'return',
            'ref_no' => 'REF-RET-001',
            'supplier_id' => $supplier->id,
            'warehouse_id' => $displayWarehouse->id,
            'transacted_at' => now(),
            'note' => 'Retur ke supplier',
            'created_by' => $creator->id,
            'approved_by' => $finalizer->id,
            'approved_at' => now(),
            'status' => 'approved',
        ]);

        OutboundItem::create([
            'outbound_transaction_id' => $outboundReturn->id,
            'item_id' => $outboundItem->id,
            'qty' => 7,
            'note' => 'Retur outbound qty 7',
        ]);

        $response = $this->withoutMiddleware()->getJson(route('admin.reports.returns.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 25,
        ]));

        $response->assertOk()
            ->assertJsonPath('summary.total_documents', 3)
            ->assertJsonPath('summary.customer_documents', 2)
            ->assertJsonPath('summary.outbound_documents', 1)
            ->assertJsonPath('summary.customer_received_qty', 6)
            ->assertJsonPath('summary.customer_good_qty', 5)
            ->assertJsonPath('summary.customer_damaged_qty', 1)
            ->assertJsonPath('summary.customer_lost_qty', 1)
            ->assertJsonPath('summary.outbound_qty', 7)
            ->assertJsonPath('summary.unmatched_resi', 1)
            ->assertJsonPath('analytics.sku_analytics.total_unique', 1)
            ->assertJsonPath('analytics.sku_analytics.total_lines', 2)
            ->assertJsonPath('analytics.sku_analytics.avg_per_document', 1)
            ->assertJsonPath('analytics.sku_analytics.total_target_qty', 5)
            ->assertJsonPath('analytics.sku_analytics.total_actual_qty', 6)
            ->assertJsonPath('analytics.sku_analytics.total_exception_qty', 2)
            ->assertJsonPath('analytics.sku_analytics.total_damaged_qty', 1)
            ->assertJsonPath('analytics.sku_analytics.total_lost_qty', 1)
            ->assertJsonPath('analytics.sku_analytics.rows.0.damaged_qty', 1)
            ->assertJsonPath('analytics.sku_analytics.rows.0.lost_qty', 1)
            ->assertJsonPath('analytics.sku_analytics.rows.0.damaged_rate', 14.29)
            ->assertJsonPath('analytics.sku_analytics.rows.0.lost_rate', 14.29)
            ->assertJsonPath('analytics.sku_analytics.rows.0.sku', 'SKU-RET-CUST')
            ->assertJsonPath('analytics.sku_analytics.rows.0.documents', 2)
            ->assertJsonPath('analytics.sku_analytics.rows.0.contribution_rate', 100);

        $rows = Collection::make($response->json('data'))->keyBy('code');

        $this->assertSame('Retur Customer', $rows->get('CRT-001')['source_label']);
        $this->assertSame(4, $rows->get('CRT-001')['qty_received']);
        $this->assertSame(1, $rows->get('CRT-001')['qty_damaged']);
        $this->assertSame(1, $rows->get('CRT-001')['qty_lost']);
        $this->assertSame('DMG-RET-001', $rows->get('CRT-001')['extra_reference']);

        $this->assertSame('Retur Customer', $rows->get('CRT-002')['source_label']);
        $this->assertFalse($rows->get('CRT-002')['matched']);

        $this->assertSame('Retur Outbound', $rows->get('OUT-RET-001')['source_label']);
        $this->assertSame(7, $rows->get('OUT-RET-001')['qty_total']);
        $this->assertSame('Supplier Retur', $rows->get('OUT-RET-001')['ref_primary_value']);

        $customerTabResponse = $this->withoutMiddleware()->getJson(route('admin.reports.returns.data', [
            'draw' => 2,
            'start' => 0,
            'length' => 25,
            'source' => 'customer',
        ]));

        $customerTabResponse->assertOk()
            ->assertJsonPath('summary.total_documents', 2)
            ->assertJsonPath('summary.customer_documents', 2)
            ->assertJsonPath('summary.outbound_documents', 0)
            ->assertJsonCount(2, 'data');

        $outboundTabResponse = $this->withoutMiddleware()->getJson(route('admin.reports.returns.data', [
            'draw' => 3,
            'start' => 0,
            'length' => 25,
            'source' => 'outbound',
        ]));

        $outboundTabResponse->assertOk()
            ->assertJsonPath('summary.total_documents', 1)
            ->assertJsonPath('summary.customer_documents', 0)
            ->assertJsonPath('summary.outbound_documents', 1)
            ->assertJsonPath('data.0.code', 'OUT-RET-001')
            ->assertJsonPath('data.0.sku_count', 1)
            ->assertJsonPath('analytics.sku_analytics.total_unique', 1)
            ->assertJsonPath('analytics.sku_analytics.total_target_qty', 7)
            ->assertJsonPath('analytics.sku_analytics.total_actual_qty', 7)
            ->assertJsonPath('analytics.sku_analytics.total_exception_qty', 0)
            ->assertJsonPath('analytics.sku_analytics.total_damaged_qty', 0)
            ->assertJsonPath('analytics.sku_analytics.total_lost_qty', 0)
            ->assertJsonPath('analytics.sku_analytics.rows.0.damaged_qty', 0)
            ->assertJsonPath('analytics.sku_analytics.rows.0.lost_qty', 0)
            ->assertJsonPath('analytics.sku_analytics.rows.0.sku', 'SKU-RET-OUT');

        $this->withoutMiddleware()
            ->get(route('admin.reports.returns.export', ['source' => 'customer']))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_return_report_filters_customer_completed_and_matched_rows(): void
    {
        $user = User::factory()->create();
        $resi = Resi::create([
            'id_pesanan' => 'ORD-FILTER-OK',
            'no_resi' => 'RESI-FILTER-OK',
            'tanggal_pesanan' => now()->subDays(3)->toDateString(),
            'tanggal_upload' => now()->subDays(2)->toDateString(),
            'uploader_id' => $user->id,
        ]);
        $item = Item::create([
            'sku' => 'SKU-RET-FILTER',
            'name' => 'Item Filter Retur',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
        ]);

        $completedMatched = CustomerReturn::create([
            'code' => 'CRT-FILTER-OK',
            'resi_id' => $resi->id,
            'resi_no' => 'RESI-FILTER-OK',
            'order_ref' => 'ORD-FILTER-OK',
            'received_at' => now()->subDays(2),
            'status' => CustomerReturn::STATUS_COMPLETED,
            'created_by' => $user->id,
            'inspected_by' => $user->id,
            'finalized_by' => $user->id,
        ]);

        CustomerReturnItem::create([
            'customer_return_id' => $completedMatched->id,
            'item_id' => $item->id,
            'expected_qty' => 1,
            'received_qty' => 1,
            'good_qty' => 1,
            'damaged_qty' => 0,
        ]);

        $inspectedUnmatched = CustomerReturn::create([
            'code' => 'CRT-FILTER-NO',
            'resi_no' => 'RESI-FILTER-NO',
            'received_at' => now()->subDay(),
            'status' => CustomerReturn::STATUS_INSPECTED,
            'created_by' => $user->id,
            'inspected_by' => $user->id,
        ]);

        CustomerReturnItem::create([
            'customer_return_id' => $inspectedUnmatched->id,
            'item_id' => $item->id,
            'expected_qty' => 1,
            'received_qty' => 1,
            'good_qty' => 0,
            'damaged_qty' => 1,
        ]);

        $response = $this->withoutMiddleware()->getJson(route('admin.reports.returns.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 25,
            'source' => 'customer',
            'status' => CustomerReturn::STATUS_COMPLETED,
            'match_state' => 'matched',
            'q' => 'CRT-FILTER',
        ]));

        $response->assertOk()
            ->assertJsonPath('summary.total_documents', 1)
            ->assertJsonPath('summary.customer_documents', 1)
            ->assertJsonPath('summary.outbound_documents', 0)
            ->assertJsonPath('summary.unmatched_resi', 0)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'CRT-FILTER-OK')
            ->assertJsonPath('data.0.status', CustomerReturn::STATUS_COMPLETED)
            ->assertJsonPath('data.0.matched', true);
    }

    public function test_return_report_separates_inbound_returns_and_exposes_operational_analytics(): void
    {
        $creator = User::factory()->create(['name' => 'Input Retur Inbound']);
        $scanner = User::factory()->create(['name' => 'Scanner Retur Inbound']);
        $warehouse = Warehouse::firstOrCreate(
            ['code' => 'GUDANG_RETUR_INBOUND'],
            ['name' => 'Gudang Retur Inbound', 'type' => 'main']
        );
        $item = Item::create([
            'sku' => 'SKU-RET-INBOUND',
            'name' => 'Item Retur Inbound',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
            'koli_qty' => 10,
        ]);

        $transaction = InboundTransaction::create([
            'code' => 'INB-RET-001',
            'type' => 'return',
            'ref_no' => 'REF-INB-RET-001',
            'surat_jalan_no' => 'SJ-INB-RET-001',
            'warehouse_id' => $warehouse->id,
            'transacted_at' => now()->subHours(3),
            'note' => 'Retur inbound dari toko',
            'created_by' => $creator->id,
            'approved_by' => $scanner->id,
            'approved_at' => now()->subHour(),
            'status' => 'completed',
        ]);

        InboundItem::create([
            'inbound_transaction_id' => $transaction->id,
            'item_id' => $item->id,
            'qty' => 10,
            'koli' => 1,
            'input_unit' => 'koli',
            'note' => 'Target sepuluh pcs',
        ]);

        $session = InboundScanSession::create([
            'inbound_transaction_id' => $transaction->id,
            'started_by' => $scanner->id,
            'started_at' => now()->subHours(2),
            'last_scanned_by' => $scanner->id,
            'last_scanned_at' => now()->subHour(),
            'completed_by' => $scanner->id,
            'completed_at' => now()->subHour(),
            'reset_count' => 1,
        ]);

        InboundScanSessionItem::create([
            'inbound_scan_session_id' => $session->id,
            'item_id' => $item->id,
            'sku' => $item->sku,
            'item_name' => $item->name,
            'input_unit' => 'koli',
            'qty_per_koli' => 10,
            'expected_qty' => 10,
            'expected_koli' => 1,
            'scanned_qty' => 8,
            'scanned_koli' => 1,
        ]);

        $response = $this->withoutMiddleware()->getJson(route('admin.reports.returns.data', [
            'draw' => 4,
            'start' => 0,
            'length' => 25,
            'source' => 'inbound',
        ]));

        $response->assertOk()
            ->assertJsonPath('summary.total_documents', 1)
            ->assertJsonPath('summary.customer_documents', 0)
            ->assertJsonPath('summary.inbound_documents', 1)
            ->assertJsonPath('summary.outbound_documents', 0)
            ->assertJsonPath('summary.inbound_expected_qty', 10)
            ->assertJsonPath('summary.inbound_scanned_qty', 8)
            ->assertJsonPath('analytics.module', 'inbound')
            ->assertJsonPath('analytics.headline.1.value', 100)
            ->assertJsonPath('analytics.headline.2.value', 80)
            ->assertJsonPath('analytics.headline.3.value', 1)
            ->assertJsonPath('analytics.secondary.3.value', 1)
            ->assertJsonPath('data.0.code', 'INB-RET-001')
            ->assertJsonPath('data.0.status', 'completed')
            ->assertJsonPath('data.0.qty_expected', 10)
            ->assertJsonPath('data.0.qty_received', 8)
            ->assertJsonPath('data.0.qty_variance', -2)
            ->assertJsonPath('data.0.reset_count', 1)
            ->assertJsonPath('data.0.secondary_by', 'Scanner Retur Inbound')
            ->assertJsonPath('data.0.tertiary_by', 'Scanner Retur Inbound')
            ->assertJsonPath('data.0.sku_count', 1)
            ->assertJsonPath('analytics.sku_analytics.total_unique', 1)
            ->assertJsonPath('analytics.sku_analytics.total_lines', 1)
            ->assertJsonPath('analytics.sku_analytics.avg_per_document', 1)
            ->assertJsonPath('analytics.sku_analytics.total_target_qty', 10)
            ->assertJsonPath('analytics.sku_analytics.total_actual_qty', 8)
            ->assertJsonPath('analytics.sku_analytics.total_exception_qty', 2)
            ->assertJsonPath('analytics.sku_analytics.total_damaged_qty', 0)
            ->assertJsonPath('analytics.sku_analytics.total_lost_qty', 2)
            ->assertJsonPath('analytics.sku_analytics.rows.0.damaged_qty', 0)
            ->assertJsonPath('analytics.sku_analytics.rows.0.lost_qty', 2)
            ->assertJsonPath('analytics.sku_analytics.rows.0.lost_rate', 20)
            ->assertJsonPath('analytics.sku_analytics.rows.0.sku', 'SKU-RET-INBOUND')
            ->assertJsonPath('analytics.sku_analytics.rows.0.variance_qty', -2)
            ->assertJsonPath('analytics.sku_analytics.rows.0.exception_rate', 20);

        $this->withoutMiddleware()
            ->get(route('admin.reports.returns.export', ['source' => 'inbound']))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
}
