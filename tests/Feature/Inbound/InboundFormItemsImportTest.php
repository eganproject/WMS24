<?php

namespace Tests\Feature\Inbound;

use App\Http\Middleware\AuthorizeMenuPermission;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class InboundFormItemsImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_receipt_form_item_import_merges_duplicate_rows(): void
    {
        $this->withoutMiddleware(AuthorizeMenuPermission::class);

        $user = User::factory()->create();
        $itemA = Item::create([
            'sku' => 'SKU-IMPORT-001',
            'name' => 'Import Item A',
            'category_id' => 0,
            'koli_qty' => 10,
        ]);
        $itemB = Item::create([
            'sku' => 'SKU-IMPORT-002',
            'name' => 'Import Item B',
            'category_id' => 0,
            'koli_qty' => 5,
        ]);

        $file = $this->makeExcelUpload([
            ['sku', 'qty', 'koli', 'item_note'],
            ['SKU-IMPORT-001', 10, '', 'batch A'],
            ['sku-import-001', '', 1, ''],
            ['SKU-IMPORT-002', '', 2, 'batch B'],
        ]);

        $this->actingAs($user)
            ->post(route('admin.inbound.receipts.items-import'), [
                'file' => $file,
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('summary.count', 2)
            ->assertJsonPath('summary.qty', 30)
            ->assertJsonPath('summary.koli', 4)
            ->assertJsonPath('items.0.item_id', $itemA->id)
            ->assertJsonPath('items.0.qty', 20)
            ->assertJsonPath('items.0.koli', 2)
            ->assertJsonPath('items.0.note', 'batch A')
            ->assertJsonPath('items.1.item_id', $itemB->id)
            ->assertJsonPath('items.1.qty', 10)
            ->assertJsonPath('items.1.koli', 2)
            ->assertJsonPath('items.1.note', 'batch B');

        $this->assertDatabaseCount('inbound_transactions', 0);
        $this->assertDatabaseCount('inbound_items', 0);
    }

    public function test_receipt_form_item_import_rejects_unknown_sku(): void
    {
        $this->withoutMiddleware(AuthorizeMenuPermission::class);

        $user = User::factory()->create();
        Item::create([
            'sku' => 'SKU-IMPORT-OK',
            'name' => 'Import Item OK',
            'category_id' => 0,
            'koli_qty' => 12,
        ]);

        $file = $this->makeExcelUpload([
            ['sku', 'qty'],
            ['SKU-IMPORT-OK', 12],
            ['SKU-TIDAK-ADA', 5],
        ]);

        $this->actingAs($user)
            ->post(route('admin.inbound.receipts.items-import'), [
                'file' => $file,
            ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');
    }

    /**
     * @param array<int,array<int|string|null>> $rows
     */
    private function makeExcelUpload(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($rows as $rowIndex => $row) {
            foreach (array_values($row) as $columnIndex => $value) {
                $sheet->setCellValueByColumnAndRow($columnIndex + 1, $rowIndex + 1, $value);
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'inbound-items-');
        $writer = new Xlsx($spreadsheet);
        $writer->save($path);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return new UploadedFile(
            $path,
            'inbound-items.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }
}
