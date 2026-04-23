<?php

namespace Tests\Feature\Admin;

use App\Http\Middleware\AuthorizeMenuPermission;
use App\Models\Item;
use App\Models\OutboundTransaction;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\WarehouseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class OutboundReturnImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_import_accepts_koli_and_converts_it_to_qty(): void
    {
        $this->withoutMiddleware(AuthorizeMenuPermission::class);
        $this->createWarehouseFixtures();

        $user = User::factory()->create();
        $supplier = Supplier::create(['name' => 'Supplier Retur Import']);
        $item = Item::create([
            'sku' => 'SKU-OUT-IMP-001',
            'name' => 'Item Retur Import',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
            'koli_qty' => 6,
        ]);

        $file = $this->makeExcelUpload([
            ['sku', 'koli', 'supplier', 'ref_no', 'item_note', 'transacted_at'],
            ['SKU-OUT-IMP-001', 2, $supplier->name, 'RET-IMP-01', 'batch retur', now()->format('Y-m-d H:i')],
        ]);

        $this->actingAs($user)
            ->post(route('admin.outbound.returns.import'), [
                'file' => $file,
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('message', 'Import retur outbound berhasil')
            ->assertJsonPath('transactions', 1)
            ->assertJsonPath('items', 1);

        $transaction = OutboundTransaction::with('items')->firstOrFail();
        $this->assertSame('return', $transaction->type);
        $this->assertSame($supplier->id, (int) $transaction->supplier_id);
        $this->assertSame(WarehouseService::displayWarehouseId(), (int) $transaction->warehouse_id);
        $this->assertSame('RET-IMP-01', $transaction->ref_no);
        $this->assertCount(1, $transaction->items);
        $this->assertSame($item->id, (int) $transaction->items->first()->item_id);
        $this->assertSame(12, (int) $transaction->items->first()->qty);
        $this->assertSame('batch retur', $transaction->items->first()->note);
    }

    public function test_returns_import_rejects_qty_that_is_not_evenly_divisible_per_koli(): void
    {
        $this->withoutMiddleware(AuthorizeMenuPermission::class);
        $this->createWarehouseFixtures();

        $user = User::factory()->create();
        $supplier = Supplier::create(['name' => 'Supplier Retur Import']);
        Item::create([
            'sku' => 'SKU-OUT-IMP-002',
            'name' => 'Item Retur Import Invalid',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
            'koli_qty' => 8,
        ]);

        $file = $this->makeExcelUpload([
            ['sku', 'qty', 'supplier'],
            ['SKU-OUT-IMP-002', 10, $supplier->name],
        ]);

        $this->actingAs($user)
            ->post(route('admin.outbound.returns.import'), [
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

        $path = tempnam(sys_get_temp_dir(), 'outbound-return-import-');
        $writer = new Xlsx($spreadsheet);
        $writer->save($path);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return new UploadedFile(
            $path,
            'outbound-return-import.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }

    private function createWarehouseFixtures(): array
    {
        $mainWarehouse = Warehouse::firstOrCreate(
            ['code' => 'GUDANG_BESAR'],
            ['name' => 'Gudang Besar', 'type' => 'main']
        );
        $displayWarehouse = Warehouse::firstOrCreate(
            ['code' => 'GUDANG_DISPLAY'],
            ['name' => 'Gudang Display', 'type' => 'display']
        );
        $damagedWarehouse = Warehouse::firstOrCreate(
            ['code' => 'GUDANG_RUSAK'],
            ['name' => 'Gudang Rusak', 'type' => 'damaged']
        );

        return [$mainWarehouse, $displayWarehouse, $damagedWarehouse];
    }
}
