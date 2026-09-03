<?php

namespace Tests\Feature\Admin;

use App\Exports\StockMutationDetailSheet;
use App\Exports\StockMutationItemSummarySheet;
use App\Exports\StockMutationOverviewSheet;
use App\Exports\StockMutationsExport;
use App\Models\Item;
use App\Models\StockMutation;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\StockMutationReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class StockMutationsExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_exposes_export_action_and_keeps_url_filters(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->withoutMiddleware()
            ->get(route('admin.inventory.stock-mutations.index', [
                'direction' => 'in',
                'source_type' => 'inbound',
                'q' => 'SKU-TEST',
            ]));

        $response->assertOk();
        $response->assertSee('id="btn_export"', false);
        $response->assertSee('Menyiapkan Excel...', false);
        $response->assertSee("exportBtn.setAttribute('aria-busy', 'true')", false);
        $response->assertSee('value="SKU-TEST"', false);
        $response->assertSee('value="in" selected', false);
        $response->assertSee('value="inbound" selected', false);
    }

    public function test_report_uses_the_same_warehouse_direction_source_date_and_search_filters(): void
    {
        [$main, $display, $item] = $this->fixtures();

        $included = $this->mutation($main, $item, 'in', 40, 'inbound', now()->subDay(), 'PO-MATCH', 'catatan audit');
        $this->mutation($main, $item, 'out', 10, 'inbound', now()->subDay(), 'PO-MATCH', 'catatan audit');
        $this->mutation($main, $item, 'in', 20, 'adjustment', now()->subDay(), 'PO-MATCH', 'catatan audit');
        $this->mutation($display, $item, 'in', 30, 'inbound', now()->subDay(), 'PO-MATCH', 'catatan audit');
        $this->mutation($main, $item, 'in', 50, 'inbound', now()->subDays(20), 'PO-MATCH', 'catatan audit');

        $rows = (new StockMutationReport([
            'warehouse_id' => $main->id,
            'direction' => 'in',
            'source_type' => 'inbound',
            'date_from' => now()->subDays(2)->toDateString(),
            'date_to' => now()->toDateString(),
            'q' => 'audit',
        ]))->rows();

        $this->assertCount(1, $rows);
        $this->assertSame($included->id, $rows->first()->id);
    }

    public function test_export_sheets_provide_analysis_item_summary_and_auditable_details(): void
    {
        [$main, , $item] = $this->fixtures();
        $this->mutation($main, $item, 'in', 100, 'inbound', now()->subDay(), 'RCV-001', null, 0, 100);
        $this->mutation($main, $item, 'out', 30, 'qc_shipment', now(), 'QC-001', null, 100, 70);

        $report = new StockMutationReport([
            'warehouse_id' => $main->id,
            'date_from' => now()->subDays(2)->toDateString(),
            'date_to' => now()->toDateString(),
        ]);

        $overview = (new StockMutationOverviewSheet($report))->array();
        $summary = (new StockMutationItemSummarySheet($report))->collection();
        $detail = (new StockMutationDetailSheet($report))->collection();

        $this->assertSame('Jumlah Mutasi', $overview[4][0]);
        $this->assertSame(2, $overview[5][0]);
        $this->assertSame(100, $overview[5][6]);
        $this->assertSame(30, $overview[5][8]);

        $this->assertCount(1, $summary);
        $this->assertSame(100, $summary->first()[7]);
        $this->assertSame(30, $summary->first()[8]);
        $this->assertSame(70, $summary->first()[9]);
        $this->assertSame(0, $summary->first()[15]);

        $this->assertCount(2, $detail);
        $this->assertSame('QC-001', $detail->first()[16]);
        $this->assertSame('Cocok', $detail->first()[13]);
    }

    public function test_export_downloads_a_three_sheet_xlsx(): void
    {
        Excel::fake();
        $this->freezeTime();

        $this->withoutMiddleware()->get(route('admin.inventory.stock-mutations.export', ['warehouse_id' => 'all']));

        Excel::assertDownloaded('mutasi-stok-'.now()->format('YmdHis').'.xlsx', function (StockMutationsExport $export) {
            $sheets = $export->sheets();

            return count($sheets) === 3
                && $sheets[0] instanceof StockMutationOverviewSheet
                && $sheets[1] instanceof StockMutationItemSummarySheet
                && $sheets[2] instanceof StockMutationDetailSheet;
        });
    }

    public function test_generated_workbook_can_be_opened_and_contains_all_report_tabs(): void
    {
        [$main, , $item] = $this->fixtures();
        $this->mutation($main, $item, 'in', 25, 'inbound', now(), 'RCV-XLSX', null, 0, 25);

        $binary = Excel::raw(new StockMutationsExport(['warehouse_id' => $main->id]), ExcelWriter::XLSX);
        $path = tempnam(sys_get_temp_dir(), 'stock-mutation-export').'.xlsx';
        file_put_contents($path, $binary);

        try {
            $workbook = IOFactory::load($path);
            $this->assertSame(['Ringkasan Analisis', 'Rekap Item-Gudang', 'Detail Mutasi'], $workbook->getSheetNames());
            $this->assertSame('RCV-XLSX', $workbook->getSheetByName('Detail Mutasi')->getCell('Q6')->getValue());
            $this->assertSame(25, $workbook->getSheetByName('Rekap Item-Gudang')->getCell('H6')->getValue());
        } finally {
            @unlink($path);
        }
    }

    private function fixtures(): array
    {
        $main = Warehouse::firstOrCreate(['code' => 'GUDANG_BESAR'], ['name' => 'Gudang Besar', 'type' => 'main']);
        $display = Warehouse::firstOrCreate(['code' => 'GUDANG_DISPLAY'], ['name' => 'Gudang Display', 'type' => 'display']);
        $item = Item::create([
            'sku' => 'SKU-TEST',
            'name' => 'Item Pengujian',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
        ]);

        return [$main, $display, $item];
    }

    private function mutation(
        Warehouse $warehouse,
        Item $item,
        string $direction,
        int $qty,
        string $sourceType,
        $occurredAt,
        string $sourceCode,
        ?string $note = null,
        ?int $stockBefore = null,
        ?int $stockAfter = null,
    ): StockMutation {
        return StockMutation::create([
            'warehouse_id' => $warehouse->id,
            'item_id' => $item->id,
            'direction' => $direction,
            'qty' => $qty,
            'stock_before' => $stockBefore,
            'stock_after' => $stockAfter,
            'source_type' => $sourceType,
            'source_id' => random_int(1, 1000000),
            'source_code' => $sourceCode,
            'note' => $note,
            'occurred_at' => $occurredAt,
            'is_void' => false,
        ]);
    }
}
