<?php

namespace Tests\Feature\Admin;

use App\Exports\InboundReceiptsDetailSheet;
use App\Exports\InboundReceiptsExport;
use App\Exports\InboundReceiptsOverviewSheet;
use App\Exports\InboundReceiptsSummarySheet;
use App\Models\InboundItem;
use App\Models\InboundTransaction;
use App\Models\Item;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
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

    public function test_overview_sheet_summarises_period_and_ranks_top_skus(): void
    {
        $this->createReceipt('RCV-A', now()->subDays(2), 100, 5);
        $this->createReceipt('RCV-B', now()->subDay(), 300, 10);
        $this->createReceipt('RCV-OLD', now()->subDays(30), 999, 99);

        $rows = (new InboundReceiptsOverviewSheet($this->weekFilters()))->array();

        // Baris 6 = label KPI, baris 7 = nilainya (indeks array dimulai dari 0).
        $this->assertSame('Total Dokumen', $rows[5][0]);
        $this->assertSame(2, $rows[6][0]);
        $this->assertSame('Total Qty (Pcs)', $rows[5][3]);
        $this->assertSame(400, $rows[6][3]);

        $skuHeader = array_search(['SKU', 'Nama Barang', 'Total Koli', 'Total Qty', 'Kontribusi'], $rows, true);
        $this->assertNotFalse($skuHeader);
        $this->assertSame('SKU-RCV-B', $rows[$skuHeader + 1][0], 'SKU dengan qty terbanyak harus di urutan pertama.');
        $this->assertSame(300, $rows[$skuHeader + 1][3]);
        $this->assertSame(75.0, $rows[$skuHeader + 1][4]);
        $this->assertSame('SKU-RCV-A', $rows[$skuHeader + 2][0]);
    }

    /**
     * Acuan grafik dibuat sebelum sheet ditulis, jadi pergeseran satu baris saja
     * membuat grafik menunjuk sel kosong. Uji lewat file yang benar-benar ditulis.
     */
    public function test_overview_charts_point_at_the_rows_that_hold_the_data(): void
    {
        $this->createReceipt('RCV-A', now()->subDays(2), 100, 5);
        $this->createReceipt('RCV-B', now()->subDay(), 300, 10);

        $export = new InboundReceiptsExport($this->weekFilters());
        $sheet = $this->writtenOverviewSheet($export);
        $charts = $export->sheets()[0]->charts();

        $this->assertCount(4, $charts, 'Tren, top SKU, top supplier, dan sebaran status.');

        foreach ($charts as $chart) {
            $group = $chart->getPlotArea()->getPlotGroupByIndex(0);
            $category = $this->parseReference($group->getPlotCategories()[0]->getDataSource());
            $value = $this->parseReference($group->getPlotValues()[0]->getDataSource());

            $this->assertSame($category['first'], $value['first']);
            $this->assertSame($category['last'], $value['last']);

            for ($row = $category['first']; $row <= $category['last']; $row++) {
                $label = $sheet->getCell($category['column'].$row)->getValue();
                $number = $sheet->getCell($value['column'].$row)->getValue();

                $this->assertNotSame('', trim((string) $label), $chart->getName().' menunjuk label kosong di baris '.$row);
                $this->assertIsNumeric($number, $chart->getName().' menunjuk nilai non-angka di baris '.$row);
                $this->assertGreaterThan(0, $number);
            }
        }
    }

    private function writtenOverviewSheet(InboundReceiptsExport $export): Worksheet
    {
        $path = tempnam(sys_get_temp_dir(), 'rcv').'.xlsx';
        file_put_contents($path, Excel::raw($export, ExcelWriter::XLSX));

        try {
            return IOFactory::load($path)->getSheetByName('Ringkasan Grafik');
        } finally {
            @unlink($path);
        }
    }

    /** Ubah "'Sheet'!$A$11:$A$15" menjadi huruf kolom dan nomor baris. */
    private function parseReference(string $reference): array
    {
        preg_match('/\$([A-Z]+)\$(\d+)(?::\$([A-Z]+)\$(\d+))?$/', $reference, $matches);

        return [
            'column' => $matches[1],
            'first' => (int) $matches[2],
            'last' => (int) ($matches[4] ?? $matches[2]),
        ];
    }

    private function weekFilters(): array
    {
        return [
            'date_from' => now()->subDays(6)->format('Y-m-d'),
            'date_to' => now()->format('Y-m-d'),
        ];
    }

    public function test_export_uses_multiple_sheets(): void
    {
        Excel::fake();
        $this->freezeTime();

        $this->withoutMiddleware()->get(route('admin.inbound.receipts.export'));

        Excel::assertDownloaded('penerimaan-barang-'.now()->format('YmdHis').'.xlsx', function ($export) {
            $sheets = $export->sheets();

            return count($sheets) === 3
                && $sheets[0] instanceof InboundReceiptsOverviewSheet
                && $sheets[1] instanceof InboundReceiptsSummarySheet
                && $sheets[2] instanceof InboundReceiptsDetailSheet;
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
