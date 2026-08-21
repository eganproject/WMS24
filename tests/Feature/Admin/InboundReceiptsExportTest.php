<?php

namespace Tests\Feature\Admin;

use App\Exports\InboundReceiptsDetailSheet;
use App\Exports\InboundReceiptsSummarySheet;
use App\Models\InboundItem;
use App\Models\InboundTransaction;
use App\Models\Item;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class InboundReceiptsExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_receipts_page_defaults_date_filter_to_last_seven_days(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->withoutMiddleware()
            ->get(route('admin.inbound.receipts.index'));

        $response->assertOk();
        $response->assertSee('id="filter_date_from" placeholder="Dari" value="'.now()->subDays(6)->format('Y-m-d').'"', false);
        $response->assertSee('id="filter_date_to" placeholder="Sampai" value="'.now()->format('Y-m-d').'"', false);
        $response->assertSee('id="btn_export_flow"', false);
    }

    public function test_export_downloads_xlsx_file(): void
    {
        $response = $this->withoutMiddleware()->get(route('admin.inbound.receipts.export', [
            'date_from' => now()->subDays(6)->format('Y-m-d'),
            'date_to' => now()->format('Y-m-d'),
        ]));

        $response->assertOk();
        $response->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
        $this->assertStringContainsString('penerimaan-barang-', $response->headers->get('content-disposition'));
    }

    public function test_export_only_contains_rows_inside_the_date_filter(): void
    {
        $inside = $this->createReceipt('RCV-INSIDE', now()->subDays(2), 24, 2);
        $this->createReceipt('RCV-OUTSIDE', now()->subDays(30), 12, 1);

        $filters = [
            'date_from' => now()->subDays(6)->format('Y-m-d'),
            'date_to' => now()->format('Y-m-d'),
        ];

        $summary = (new InboundReceiptsSummarySheet($filters))->collection();
        $detail = (new InboundReceiptsDetailSheet($filters))->collection();

        $this->assertCount(1, $summary);
        $this->assertSame($inside->code, $summary->first()[1]);
        $this->assertSame(24, $summary->first()[11]);

        $this->assertCount(1, $detail);
        $this->assertSame($inside->code, $detail->first()[1]);
        $this->assertSame('SKU-RCV-INSIDE', $detail->first()[8]);
    }

    public function test_export_excludes_non_receipt_transactions(): void
    {
        $this->createReceipt('RET-ONLY', now()->subDay(), 10, 1, 'return');

        $rows = (new InboundReceiptsSummarySheet([
            'date_from' => now()->subDays(6)->format('Y-m-d'),
            'date_to' => now()->format('Y-m-d'),
        ]))->collection();

        $this->assertCount(0, $rows);
    }

    public function test_export_uses_multiple_sheets(): void
    {
        Excel::fake();
        $this->freezeTime();

        $this->withoutMiddleware()->get(route('admin.inbound.receipts.export'));

        Excel::assertDownloaded('penerimaan-barang-'.now()->format('YmdHis').'.xlsx', function ($export) {
            $sheets = $export->sheets();

            return count($sheets) === 2
                && $sheets[0] instanceof InboundReceiptsSummarySheet
                && $sheets[1] instanceof InboundReceiptsDetailSheet;
        });
    }

    private function createReceipt(string $code, $transactedAt, int $qty, int $koli, string $type = 'receipt'): InboundTransaction
    {
        $warehouse = Warehouse::firstOrCreate(['code' => 'GUDANG_BESAR'], [
            'name' => 'Gudang Besar',
            'type' => 'main',
        ]);

        $item = Item::create([
            'sku' => 'SKU-'.$code,
            'name' => 'Item '.$code,
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
            'koli_qty' => 12,
        ]);

        $transaction = InboundTransaction::create([
            'code' => $code,
            'type' => $type,
            'warehouse_id' => $warehouse->id,
            'transacted_at' => $transactedAt,
            'status' => 'pending_scan',
        ]);

        InboundItem::create([
            'inbound_transaction_id' => $transaction->id,
            'item_id' => $item->id,
            'qty' => $qty,
            'koli' => $koli,
        ]);

        return $transaction->fresh();
    }
}
