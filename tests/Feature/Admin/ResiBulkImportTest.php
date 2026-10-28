<?php

namespace Tests\Feature\Admin;

use App\Models\Item;
use App\Models\ItemBundleComponent;
use App\Models\PickingList;
use App\Models\Resi;
use App\Models\ResiDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ResiBulkImportTest extends TestCase
{
    use RefreshDatabase;

    private function seedItems(): void
    {
        Item::create(['sku' => 'BULK-A', 'name' => 'Bulk A', 'item_type' => Item::TYPE_SINGLE, 'category_id' => 0, 'safety_stock' => 0]);
        $b = Item::create(['sku' => 'BULK-B', 'name' => 'Bulk B', 'item_type' => Item::TYPE_SINGLE, 'category_id' => 0, 'safety_stock' => 0]);
        $bundle = Item::create(['sku' => 'BULK-BUNDLE', 'name' => 'Bulk Bundle', 'item_type' => Item::TYPE_BUNDLE, 'category_id' => 0, 'safety_stock' => 0]);
        ItemBundleComponent::create([
            'bundle_item_id' => $bundle->id,
            'component_item_id' => $b->id,
            'required_qty' => 2,
        ]);
    }

    /** @return array<int,array<int,mixed>> */
    private function bulkRows(int $orders): array
    {
        $rows = [['ID Pesanan', 'AWB No. Tracking', 'SKU', 'Jumlah', 'Tanggal Pembuatan', 'Kurir']];
        for ($i = 1; $i <= $orders; $i++) {
            $rows[] = ["ORD-BULK-{$i}", "AWB-BULK-{$i}", 'BULK-A', 1, '2026-05-15', $i % 2 ? 'JNE' : 'jne'];
            $rows[] = ["ORD-BULK-{$i}", "AWB-BULK-{$i}", 'BULK-BUNDLE', 1, '2026-05-15', 'JNE'];
        }

        return $rows;
    }

    public function test_large_file_is_imported_across_chunks_and_picking_list_is_correct(): void
    {
        $this->travelTo(Carbon::parse('2026-05-15 10:00:00'));
        $this->seedItems();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withoutMiddleware()
            ->post(route('admin.inventory.resi-import.import'), [
                'file' => $this->makeExcelUpload($this->bulkRows(450)),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('resis', 450)
            ->assertJsonPath('details', 900);

        $this->assertSame(450, Resi::count());
        $this->assertSame(900, ResiDetail::count());
        $this->assertSame(1, Resi::query()->distinct()->count('kurir_id'));
        $this->assertSame(450, (int) PickingList::where('sku', 'BULK-A')->value('qty'));
        $this->assertSame(900, (int) PickingList::where('sku', 'BULK-B')->value('qty'));
        $this->assertSame(0, PickingList::where('sku', 'BULK-BUNDLE')->count());

        // Re-importing the same file must not double the picking list.
        $this->actingAs($user)
            ->withoutMiddleware()
            ->post(route('admin.inventory.resi-import.import'), [
                'file' => $this->makeExcelUpload($this->bulkRows(450)),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('resis', 450);

        $this->assertSame(450, Resi::count());
        $this->assertSame(900, ResiDetail::count());
        $this->assertSame(450, (int) PickingList::where('sku', 'BULK-A')->value('qty'));
        $this->assertSame(900, (int) PickingList::where('sku', 'BULK-B')->value('qty'));
    }

    public function test_unknown_sku_anywhere_in_file_saves_nothing(): void
    {
        $this->travelTo(Carbon::parse('2026-05-15 10:00:00'));
        $this->seedItems();
        $rows = $this->bulkRows(300);
        $rows[] = ['ORD-BULK-UNKNOWN', 'AWB-BULK-UNKNOWN', 'SKU-TIDAK-ADA', 1, '2026-05-15', 'JNE'];

        $this->actingAs(User::factory()->create())
            ->withoutMiddleware()
            ->post(route('admin.inventory.resi-import.import'), [
                'file' => $this->makeExcelUpload($rows),
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable();

        $this->assertSame(0, Resi::count());
        $this->assertSame(0, PickingList::count());
    }

    public function test_second_import_is_rejected_while_one_is_running(): void
    {
        $this->seedItems();
        $lock = Cache::lock('resi-import:running', 60);
        $this->assertTrue($lock->get());

        try {
            $this->actingAs(User::factory()->create())
                ->withoutMiddleware()
                ->post(route('admin.inventory.resi-import.import'), [
                    'file' => $this->makeExcelUpload($this->bulkRows(1)),
                ], ['Accept' => 'application/json'])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('file');
        } finally {
            $lock->release();
        }

        $this->assertSame(0, Resi::count());
    }

    /** @param array<int,array<int,mixed>> $rows */
    private function makeExcelUpload(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1');

        $path = tempnam(sys_get_temp_dir(), 'resi-bulk-import-');
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return new UploadedFile(
            $path,
            'resi-bulk-import.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }
}
