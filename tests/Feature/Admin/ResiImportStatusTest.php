<?php

namespace Tests\Feature\Admin;

use App\Models\Item;
use App\Models\PickingList;
use App\Models\QcResiScan;
use App\Models\Resi;
use App\Models\ResiDetail;
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

    public function test_import_resi_rejects_duplicate_no_resi_in_same_file_for_different_orders(): void
    {
        $file = $this->makeExcelUpload([
            ['ID Pesanan', 'AWB No. Tracking', 'SKU', 'Jumlah', 'Tanggal Pembuatan'],
            ['ORD-DUP-001', 'DUP-RESI-001', 'SKU-DUP-001', 1, '2026-05-15'],
            ['ORD-DUP-002', 'DUP-RESI-001', 'SKU-DUP-002', 1, '2026-05-15'],
        ]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withoutMiddleware()
            ->post(route('admin.inventory.resi-import.import'), [
                'file' => $file,
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $this->assertSame(0, Resi::whereIn('id_pesanan', ['ORD-DUP-001', 'ORD-DUP-002'])->count());
    }

    public function test_import_resi_rejects_no_resi_already_used_by_another_order(): void
    {
        $user = User::factory()->create();
        Resi::create([
            'id_pesanan' => 'ORD-EXISTING-001',
            'tanggal_pesanan' => '2026-05-14',
            'tanggal_upload' => '2026-05-14',
            'no_resi' => 'DUP-RESI-DB-001',
            'uploader_id' => $user->id,
        ]);

        $file = $this->makeExcelUpload([
            ['ID Pesanan', 'AWB No. Tracking', 'SKU', 'Jumlah', 'Tanggal Pembuatan'],
            ['ORD-NEW-001', 'DUP-RESI-DB-001', 'SKU-DUP-DB-001', 1, '2026-05-15'],
        ]);

        $this->actingAs($user)
            ->withoutMiddleware()
            ->post(route('admin.inventory.resi-import.import'), [
                'file' => $file,
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $this->assertNull(Resi::where('id_pesanan', 'ORD-NEW-001')->first());
    }

    public function test_unprocessed_resi_can_be_deleted_and_removed_from_picking_list(): void
    {
        Item::create([
            'sku' => 'SKU-DELETE-RESI-001',
            'name' => 'Item Delete Resi',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
            'safety_stock' => 0,
        ]);
        $user = User::factory()->create();
        $resi = Resi::create([
            'id_pesanan' => 'ORD-DELETE-001',
            'tanggal_pesanan' => '2026-05-15',
            'tanggal_upload' => '2026-05-15',
            'no_resi' => 'RESI-DELETE-001',
            'uploader_id' => $user->id,
            'status' => 'active',
        ]);
        ResiDetail::create([
            'resi_id' => $resi->id,
            'sku' => 'SKU-DELETE-RESI-001',
            'qty' => 2,
        ]);
        PickingList::create([
            'list_date' => '2026-05-15',
            'sku' => 'SKU-DELETE-RESI-001',
            'qty' => 2,
            'remaining_qty' => 2,
        ]);

        $this->actingAs($user)
            ->withoutMiddleware()
            ->deleteJson(route('admin.inventory.resi-import.destroy', $resi->id))
            ->assertOk()
            ->assertJsonPath('message', 'Resi berhasil dihapus.');

        $this->assertNull(Resi::find($resi->id));
        $this->assertSame(0, ResiDetail::where('resi_id', $resi->id)->count());
        $pickingList = PickingList::where('sku', 'SKU-DELETE-RESI-001')->first();
        $this->assertTrue($pickingList === null || ((int) $pickingList->qty === 0 && (int) $pickingList->remaining_qty === 0));
    }

    public function test_processed_resi_cannot_be_deleted_after_qc_scan(): void
    {
        $user = User::factory()->create();
        $resi = Resi::create([
            'id_pesanan' => 'ORD-DELETE-QC-001',
            'tanggal_pesanan' => '2026-05-15',
            'tanggal_upload' => '2026-05-15',
            'no_resi' => 'RESI-DELETE-QC-001',
            'uploader_id' => $user->id,
            'status' => 'active',
        ]);
        QcResiScan::create([
            'resi_id' => $resi->id,
            'scan_type' => 'resi',
            'scan_code' => $resi->no_resi,
            'status' => 'draft',
            'scanned_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->withoutMiddleware()
            ->deleteJson(route('admin.inventory.resi-import.destroy', $resi->id))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Resi sudah masuk proses QC atau scan out, tidak bisa dihapus.');

        $this->assertNotNull(Resi::find($resi->id));
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
