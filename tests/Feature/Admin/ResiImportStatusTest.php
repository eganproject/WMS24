<?php

namespace Tests\Feature\Admin;

use App\Models\Item;
use App\Models\PickingList;
use App\Models\Resi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ResiImportStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_resi_with_dibatalkan_status_becomes_canceled_and_skips_picking_list(): void
    {
        Item::create([
            'sku' => 'SKU-CANCEL-IMPORT-001',
            'name' => 'Item Cancel Import',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
            'safety_stock' => 0,
        ]);

        $file = $this->makeExcelUpload([
            ['ID Pesanan', 'SKU', 'Jumlah', 'Tanggal Pembuatan', 'Status'],
            ['ORD-CANCEL-001', 'SKU-CANCEL-IMPORT-001', 2, '2026-05-15', 'Dibatalkan'],
        ]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withoutMiddleware()
            ->post(route('admin.inventory.resi-import.import'), [
                'file' => $file,
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('resis', 1)
            ->assertJsonPath('details', 1);

        $resi = Resi::where('id_pesanan', 'ORD-CANCEL-001')->first();

        $this->assertNotNull($resi);
        $this->assertSame('canceled', $resi->status);
        $this->assertNotNull($resi->canceled_at);
        $this->assertSame('Status import: Dibatalkan', $resi->cancel_reason);
        $this->assertSame(0, PickingList::where('sku', 'SKU-CANCEL-IMPORT-001')->count());
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

        $path = tempnam(sys_get_temp_dir(), 'resi-import-status-');
        $writer = new Xlsx($spreadsheet);
        $writer->save($path);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return new UploadedFile(
            $path,
            'resi-import-status.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }
}
