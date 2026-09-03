<?php

namespace Tests\Feature\Admin;

use App\Exports\OutboundManualDetailSheet;
use App\Exports\OutboundManualDocumentsSheet;
use App\Exports\OutboundManualExport;
use App\Exports\OutboundManualItemSummarySheet;
use App\Exports\OutboundManualOverviewSheet;
use App\Models\Item;
use App\Models\OutboundItem;
use App\Models\OutboundQcSession;
use App\Models\OutboundQcSessionItem;
use App\Models\OutboundTransaction;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\OutboundManualQcStatus;
use App\Support\OutboundManualReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class OutboundManualExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_exposes_export_action_loading_indicator_and_keeps_url_filters(): void
    {
        $warehouse = $this->warehouse('WH-EXPORT', 'Gudang Export');

        $response = $this->actingAs(User::factory()->create())
            ->withoutMiddleware()
            ->get(route('admin.outbound.manuals.index', [
                'warehouse_id' => $warehouse->id,
                'status' => OutboundManualQcStatus::PENDING_QC,
                'q' => 'MNL-TEST',
            ]));

        $response->assertOk();
        $response->assertSee('id="btn_export_flow"', false);
        $response->assertSee('Menyiapkan Excel...', false);
        $response->assertSee("exportBtn.setAttribute('aria-busy', 'true')", false);
        $response->assertSee('value="MNL-TEST"', false);
        $response->assertSee('value="'.$warehouse->id.'" selected', false);
        $response->assertSee('value="'.OutboundManualQcStatus::PENDING_QC.'" selected', false);
    }

    public function test_report_applies_page_warehouse_status_date_and_search_filters(): void
    {
        $main = $this->warehouse('WH-MAIN', 'Gudang Utama');
        $other = $this->warehouse('WH-OTHER', 'Gudang Lain');
        $item = $this->item('SKU-FILTER', 'Item Filter');

        $included = $this->transaction($main, 'MNL-INCLUDED', now()->subDay(), OutboundManualQcStatus::PENDING_QC, 'Penerima Audit');
        $this->addItem($included, $item, 15);
        $this->transaction($main, 'MNL-WRONG-STATUS', now()->subDay(), OutboundManualQcStatus::APPROVED, 'Penerima Audit');
        $this->transaction($other, 'MNL-WRONG-WH', now()->subDay(), OutboundManualQcStatus::PENDING_QC, 'Penerima Audit');
        $this->transaction($main, 'MNL-OLD', now()->subDays(30), OutboundManualQcStatus::PENDING_QC, 'Penerima Audit');

        OutboundTransaction::create([
            'code' => 'PICKER-NOT-MANUAL',
            'type' => 'picker',
            'warehouse_id' => $main->id,
            'transacted_at' => now()->subDay(),
            'status' => OutboundManualQcStatus::PENDING_QC,
            'recipient_name' => 'Penerima Audit',
        ]);

        $report = new OutboundManualReport([
            'warehouse_id' => $main->id,
            'status' => OutboundManualQcStatus::PENDING_QC,
            'date_from' => now()->subDays(2)->toDateString(),
            'date_to' => now()->toDateString(),
            'q' => 'Audit',
        ]);

        $rows = $report->transactionQuery()->get();
        $this->assertCount(1, $rows);
        $this->assertSame($included->id, $rows->first()->id);
        $this->assertSame(1, $report->documentCount());
        $this->assertSame(1, $report->detailCount());

        $this->actingAs(User::factory()->create())
            ->withoutMiddleware()
            ->getJson(route('admin.outbound.manuals.data', [
                'warehouse_id' => $main->id,
                'status' => OutboundManualQcStatus::PENDING_QC,
                'date_from' => now()->subDays(2)->toDateString(),
                'date_to' => now()->toDateString(),
                'q' => 'Audit',
                'start' => 0,
                'length' => 10,
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $included->id);
    }

    public function test_export_sheets_provide_qc_analysis_item_summary_documents_and_details(): void
    {
        [$report, $transaction] = $this->reportWithQcProgress();

        $overview = (new OutboundManualOverviewSheet($report))->array();
        $itemSummary = (new OutboundManualItemSummarySheet($report))->collection();
        $documents = (new OutboundManualDocumentsSheet($report))->collection();
        $details = (new OutboundManualDetailSheet($report))->collection();

        $this->assertSame('Total Dokumen', $overview[4][0]);
        $this->assertSame(1, $overview[5][0]);
        $this->assertSame(2, $overview[5][2]);
        $this->assertSame(34, $overview[5][4]);
        $this->assertSame(23, $overview[5][6]);
        $this->assertEqualsWithDelta(23 / 34, $overview[5][8], 0.0001);

        $this->assertCount(2, $itemSummary);
        $this->assertSame('SKU-BIG', $itemSummary->first()[1]);
        $this->assertSame(24, $itemSummary->first()[4]);
        $this->assertSame(18, $itemSummary->first()[6]);
        $this->assertSame(6, $itemSummary->first()[7]);

        $this->assertCount(1, $documents);
        $this->assertSame($transaction->code, $documents->first()[1]);
        $this->assertSame(34, $documents->first()[13]);
        $this->assertSame(23, $documents->first()[15]);
        $this->assertSame(11, $documents->first()[16]);

        $this->assertCount(2, $details);
        $this->assertSame('SKU-BIG', $details->first()[8]);
        $this->assertSame(24, $details->first()[12]);
        $this->assertSame(18, $details->first()[14]);
    }

    public function test_export_endpoint_downloads_a_four_sheet_xlsx(): void
    {
        Excel::fake();
        $this->freezeTime();

        $this->withoutMiddleware()->get(route('admin.outbound.manuals.export', ['warehouse_id' => 'all']));

        Excel::assertDownloaded('outbound-manual-'.now()->format('YmdHis').'.xlsx', function (OutboundManualExport $export) {
            $sheets = $export->sheets();

            return count($sheets) === 4
                && $sheets[0] instanceof OutboundManualOverviewSheet
                && $sheets[1] instanceof OutboundManualItemSummarySheet
                && $sheets[2] instanceof OutboundManualDocumentsSheet
                && $sheets[3] instanceof OutboundManualDetailSheet;
        });
    }

    public function test_generated_workbook_opens_and_contains_the_report_tabs(): void
    {
        [$report, $transaction] = $this->reportWithQcProgress();
        $binary = Excel::raw(new OutboundManualExport([
            'warehouse_id' => $transaction->warehouse_id,
            'q' => $transaction->code,
        ]), ExcelWriter::XLSX);
        $path = tempnam(sys_get_temp_dir(), 'outbound-manual-export').'.xlsx';
        file_put_contents($path, $binary);

        try {
            $workbook = IOFactory::load($path);
            $this->assertSame(['Ringkasan Analisis', 'Rekap Item', 'Daftar Dokumen', 'Detail Item'], $workbook->getSheetNames());
            $this->assertSame($transaction->code, $workbook->getSheetByName('Daftar Dokumen')->getCell('B6')->getValue());
            $this->assertSame('SKU-BIG', $workbook->getSheetByName('Rekap Item')->getCell('B6')->getValue());
            $this->assertSame(23, $workbook->getSheetByName('Ringkasan Analisis')->getCell('G6')->getValue());
        } finally {
            @unlink($path);
        }
    }

    private function reportWithQcProgress(): array
    {
        $warehouse = $this->warehouse('GUDANG_BESAR', 'Gudang Besar');
        $big = $this->item('SKU-BIG', 'Item Volume Besar', 12);
        $small = $this->item('SKU-SMALL', 'Item Volume Kecil', 10);
        $transaction = $this->transaction($warehouse, 'MNL-QC-001', now(), OutboundManualQcStatus::QC_SCANNING, 'PT Penerima');
        $this->addItem($transaction, $big, 24, 'Prioritas');
        $this->addItem($transaction, $small, 10);

        $session = OutboundQcSession::create([
            'outbound_transaction_id' => $transaction->id,
            'started_at' => now()->subHour(),
        ]);
        OutboundQcSessionItem::create([
            'outbound_qc_session_id' => $session->id,
            'item_id' => $big->id,
            'sku' => $big->sku,
            'item_name' => $big->name,
            'expected_qty' => 24,
            'scanned_qty' => 18,
        ]);
        OutboundQcSessionItem::create([
            'outbound_qc_session_id' => $session->id,
            'item_id' => $small->id,
            'sku' => $small->sku,
            'item_name' => $small->name,
            'expected_qty' => 10,
            'scanned_qty' => 5,
        ]);

        return [new OutboundManualReport(['warehouse_id' => $warehouse->id]), $transaction];
    }

    private function warehouse(string $code, string $name): Warehouse
    {
        return Warehouse::firstOrCreate(['code' => $code], ['name' => $name, 'type' => 'main']);
    }

    private function item(string $sku, string $name, int $koliQty = 1): Item
    {
        return Item::create([
            'sku' => $sku,
            'name' => $name,
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
            'koli_qty' => $koliQty,
        ]);
    }

    private function transaction(Warehouse $warehouse, string $code, $date, string $status, string $recipient): OutboundTransaction
    {
        return OutboundTransaction::create([
            'code' => $code,
            'type' => 'manual',
            'warehouse_id' => $warehouse->id,
            'transacted_at' => $date,
            'status' => $status,
            'recipient_name' => $recipient,
            'surat_jalan_no' => 'SJ-'.$code,
        ]);
    }

    private function addItem(OutboundTransaction $transaction, Item $item, int $qty, ?string $note = null): OutboundItem
    {
        return OutboundItem::create([
            'outbound_transaction_id' => $transaction->id,
            'item_id' => $item->id,
            'qty' => $qty,
            'note' => $note,
        ]);
    }
}
