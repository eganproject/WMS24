<?php

namespace Tests\Feature\Admin;

use App\Exports\StockFlowImportTemplateExport;
use App\Http\Middleware\AuthorizeMenuPermission;
use App\Models\InboundTransaction;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\OutboundTransaction;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class StockFlowImportTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(AuthorizeMenuPermission::class);
        $this->createWarehouses();
    }

    public function test_outbound_manual_and_inbound_return_pages_offer_the_correct_templates(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)
            ->get(route('admin.outbound.manuals.index'))
            ->assertOk()
            ->assertSee(route('admin.outbound.manuals.template'), false)
            ->assertSee('Unduh Template Outbound Manual')
            ->assertSee('recipient_name');

        $this->actingAs($user)
            ->get(route('admin.inbound.returns.index'))
            ->assertOk()
            ->assertSee(route('admin.inbound.returns.template'), false)
            ->assertSee(route('admin.inbound.returns.items-template'), false)
            ->assertSee('Unduh Template Retur Inbound')
            ->assertSee('Unduh Template Item');
    }

    public function test_download_endpoints_return_xlsx_files_with_clear_filenames(): void
    {
        Carbon::setTestNow('2026-09-03 10:11:12');
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)
            ->get(route('admin.outbound.manuals.template'))
            ->assertOk()
            ->assertDownload('outbound-manual-template-20260903101112.xlsx');

        $this->actingAs($user)
            ->get(route('admin.inbound.returns.template'))
            ->assertOk()
            ->assertDownload('retur-inbound-template-20260903101112.xlsx');

        $this->actingAs($user)
            ->get(route('admin.inbound.returns.items-template'))
            ->assertOk()
            ->assertDownload('retur-inbound-item-template-20260903101112.xlsx');
    }

    public function test_each_workbook_has_exact_headers_guidance_references_and_validation(): void
    {
        Item::create([
            'sku' => 'SKU-REF-001',
            'name' => 'Item Referensi',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
            'koli_qty' => 12,
        ]);

        $profiles = [
            StockFlowImportTemplateExport::OUTBOUND_MANUAL => [
                'sku', 'qty', 'koli', 'warehouse', 'ref_no', 'surat_jalan_no', 'surat_jalan_at',
                'recipient_name', 'recipient_phone', 'recipient_address', 'note', 'item_note', 'transacted_at',
            ],
            StockFlowImportTemplateExport::INBOUND_RETURN => [
                'sku', 'qty', 'koli', 'input_unit', 'ref_no', 'surat_jalan_no', 'surat_jalan_at', 'note', 'item_note', 'transacted_at',
            ],
            StockFlowImportTemplateExport::INBOUND_RETURN_ITEMS => [
                'sku', 'qty', 'koli', 'input_unit', 'item_note',
            ],
        ];

        foreach ($profiles as $profile => $expectedHeaders) {
            $content = Excel::raw(new StockFlowImportTemplateExport($profile), ExcelWriter::XLSX);
            $path = tempnam(sys_get_temp_dir(), 'stock-flow-template-');
            file_put_contents($path, $content);

            $workbook = IOFactory::load($path);
            $this->assertSame(['Data Import', 'Contoh & Panduan', 'Referensi Master'], $workbook->getSheetNames());

            $dataSheet = $workbook->getSheetByName('Data Import');
            $actualHeaders = array_slice(
                $dataSheet->rangeToArray('A1:'.$dataSheet->getHighestColumn().'1', null, true, false)[0],
                0,
                count($expectedHeaders)
            );
            $this->assertSame($expectedHeaders, $actualHeaders);
            $this->assertSame(
                array_fill(0, count($expectedHeaders), null),
                array_slice($dataSheet->rangeToArray('A2:'.$dataSheet->getHighestColumn().'2')[0], 0, count($expectedHeaders))
            );
            $this->assertTrue($dataSheet->getAutoFilter()->getRange() !== '');
            $this->assertSame('list', $dataSheet->getCell('D2')->getDataValidation()->getType());

            $guideSheet = $workbook->getSheetByName('Contoh & Panduan');
            $this->assertStringContainsString('Template Import', (string) $guideSheet->getCell('A1')->getValue());
            $this->assertStringContainsString('ATURAN PENTING', collect($guideSheet->toArray())->flatten()->implode(' '));

            $referenceSheet = $workbook->getSheetByName('Referensi Master');
            $this->assertStringContainsString('SKU-REF-001', collect($referenceSheet->toArray())->flatten()->implode(' '));
            $this->assertStringContainsString('GUDANG_DISPLAY', collect($referenceSheet->toArray())->flatten()->implode(' '));

            $workbook->disconnectWorksheets();
            unlink($path);
        }
    }

    public function test_outbound_manual_import_accepts_template_recipient_case_insensitive_sku_and_excel_dates(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $warehouse = Warehouse::where('code', 'GUDANG_DISPLAY')->firstOrFail();
        $item = $this->createItem('SKU-MNL-001', 10);
        ItemStock::create(['item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'stock' => 20]);
        $excelDate = ExcelDate::PHPToExcel(Carbon::parse('2026-09-01 08:30:00')->toDateTime());

        $file = $this->makeExcelUpload([
            ['sku', 'qty', 'koli', 'warehouse', 'ref_no', 'surat_jalan_no', 'surat_jalan_at', 'recipient_name', 'recipient_phone', 'recipient_address', 'note', 'item_note', 'transacted_at'],
            ['sku-mnl-001', 5, '', 'GUDANG_DISPLAY', 'MNL-TEST-001', 'SJ-MNL-TEST-001', $excelDate, 'Budi', '08123456789', 'Jakarta', 'Tes import', 'Segel utuh', $excelDate],
        ], 'outbound-manual.xlsx');

        $this->actingAs($user)
            ->post(route('admin.outbound.manuals.import'), ['file' => $file], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('transactions', 1)
            ->assertJsonPath('items', 1);

        $transaction = OutboundTransaction::with('items')->firstOrFail();
        $this->assertSame('Budi', $transaction->recipient_name);
        $this->assertSame('08123456789', $transaction->recipient_phone);
        $this->assertSame('Jakarta', $transaction->recipient_address);
        $this->assertSame('2026-09-01 08:30', $transaction->transacted_at->format('Y-m-d H:i'));
        $this->assertSame('2026-09-01', $transaction->surat_jalan_at->format('Y-m-d'));
        $this->assertSame(5, (int) $transaction->items->first()->qty);
    }

    public function test_inbound_return_import_accepts_template_pcs_case_insensitive_sku_and_excel_dates(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $warehouse = Warehouse::where('code', 'GUDANG_DISPLAY')->firstOrFail();
        $item = $this->createItem('SKU-RET-IN-001', 10);
        $excelDate = ExcelDate::PHPToExcel(Carbon::parse('2026-09-02 09:45:00')->toDateTime());

        $file = $this->makeExcelUpload([
            ['sku', 'qty', 'koli', 'input_unit', 'ref_no', 'surat_jalan_no', 'surat_jalan_at', 'note', 'item_note', 'transacted_at'],
            ['sku-ret-in-001', 4, '', 'pcs', 'RET-IN-TEST-001', 'SJ-RET-IN-TEST-001', $excelDate, 'Tes retur', 'Barang satuan', $excelDate],
        ], 'retur-inbound.xlsx');

        $this->actingAs($user)
            ->post(route('admin.inbound.returns.import'), [
                'file' => $file,
                'warehouse_id' => $warehouse->id,
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('transactions', 1)
            ->assertJsonPath('items', 1);

        $transaction = InboundTransaction::with('items')->firstOrFail();
        $this->assertSame($warehouse->id, (int) $transaction->warehouse_id);
        $this->assertSame('2026-09-02 09:45', $transaction->transacted_at->format('Y-m-d H:i'));
        $this->assertSame('2026-09-02', $transaction->surat_jalan_at->format('Y-m-d'));
        $this->assertSame(4, (int) $transaction->items->first()->qty);
        $this->assertSame('pcs', $transaction->items->first()->input_unit);
    }

    private function createItem(string $sku, int $koliQty): Item
    {
        return Item::create([
            'sku' => $sku,
            'name' => 'Item '.$sku,
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
            'koli_qty' => $koliQty,
        ]);
    }

    /** @param array<int,array<int,mixed>> $rows */
    private function makeExcelUpload(array $rows, string $name): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray($rows);
        $path = tempnam(sys_get_temp_dir(), 'stock-flow-import-');
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return new UploadedFile(
            $path,
            $name,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }

    private function createWarehouses(): void
    {
        Warehouse::firstOrCreate(['code' => 'GUDANG_BESAR'], ['name' => 'Gudang Besar', 'type' => 'main']);
        Warehouse::firstOrCreate(['code' => 'GUDANG_DISPLAY'], ['name' => 'Gudang Display', 'type' => 'display']);
        Warehouse::firstOrCreate(['code' => 'GUDANG_RUSAK'], ['name' => 'Gudang Rusak', 'type' => 'damaged']);
    }
}
