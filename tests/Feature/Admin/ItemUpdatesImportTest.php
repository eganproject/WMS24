<?php

namespace Tests\Feature\Admin;

use App\Exports\ItemUpdatesTemplateExport;
use App\Imports\ItemUpdatesImport;
use App\Models\Category;
use App\Models\Item;
use App\Models\User;
use App\Support\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class ItemUpdatesImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_updates_only_selected_field_and_preserves_locked_data(): void
    {
        $item = Item::create([
            'sku' => 'SKU-UPDATE-001',
            'name' => 'Nama Lama',
            'item_type' => Item::TYPE_SINGLE,
            'status' => Item::STATUS_INACTIVE,
            'category_id' => 0,
            'address' => 'KAB',
            'description' => 'Deskripsi lama',
            'safety_stock' => 7,
            'koli_qty' => 24,
        ]);

        $import = new ItemUpdatesImport(['name']);
        $import->collection(collect([
            new Collection(['sku' => 'SKU-UPDATE-001', 'name' => 'Nama Baru']),
        ]));

        $item->refresh();
        $this->assertSame('SKU-UPDATE-001', $item->sku);
        $this->assertSame('Nama Baru', $item->name);
        $this->assertSame(Item::STATUS_INACTIVE, $item->status);
        $this->assertSame('KAB', $item->address);
        $this->assertSame('Deskripsi lama', $item->description);
        $this->assertSame(7, $item->safety_stock);
        $this->assertSame(24, $item->koli_qty);
        $this->assertSame(1, $import->processed);
        $this->assertSame(1, $import->updated);
        $this->assertSame(0, $import->unchanged);
    }

    public function test_import_updates_all_allowed_fields_from_selected_template(): void
    {
        $parent = Category::create(['name' => 'Elektronik', 'parent_id' => 0]);
        $category = Category::create(['name' => 'Aksesoris', 'parent_id' => $parent->id]);
        $item = Item::create([
            'sku' => 'SKU-UPDATE-002',
            'name' => 'Nama Lama',
            'item_type' => Item::TYPE_SINGLE,
            'status' => Item::STATUS_ACTIVE,
            'category_id' => 0,
            'description' => 'Lama',
            'safety_stock' => 1,
            'koli_qty' => 12,
        ]);

        $fields = ['name', 'status', 'category', 'address', 'description', 'safety_stock'];
        $import = new ItemUpdatesImport($fields);
        $import->collection(collect([
            new Collection([
                'sku' => $item->sku,
                'name' => 'Nama Lengkap Baru',
                'status' => 'inactive',
                'parent_category' => 'Elektronik',
                'category' => 'Aksesoris',
                'address' => 'KAB-A-3-5',
                'description' => '',
                'safety_stock' => '9',
            ]),
        ]));

        $item->refresh();
        $this->assertSame('Nama Lengkap Baru', $item->name);
        $this->assertSame(Item::STATUS_INACTIVE, $item->status);
        $this->assertSame($category->id, $item->category_id);
        $this->assertSame('KAB-A-03-05', $item->address);
        $this->assertNotNull($item->location_id);
        $this->assertNull($item->description);
        $this->assertSame(9, $item->safety_stock);
        $this->assertSame(12, $item->koli_qty);
    }

    public function test_import_rejects_locked_or_unexpected_columns(): void
    {
        Item::create([
            'sku' => 'SKU-UPDATE-003',
            'name' => 'Nama Aman',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
            'koli_qty' => 6,
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('header tidak diizinkan: koli_qty');

        $import = new ItemUpdatesImport(['name']);
        $import->collection(collect([
            new Collection([
                'sku' => 'SKU-UPDATE-003',
                'name' => 'Nama Baru',
                'koli_qty' => 99,
            ]),
        ]));
    }

    public function test_import_never_creates_unknown_sku(): void
    {
        try {
            (new ItemUpdatesImport(['name']))->collection(collect([
                new Collection(['sku' => 'SKU-TIDAK-ADA', 'name' => 'Tidak Boleh Dibuat']),
            ]));
            $this->fail('SKU yang tidak ditemukan seharusnya ditolak.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('tidak ditemukan', $exception->errors()['file'][0] ?? '');
        }

        $this->assertDatabaseMissing('items', ['sku' => 'SKU-TIDAK-ADA']);
    }

    public function test_template_contains_sku_current_values_and_only_selected_fields(): void
    {
        Item::create([
            'sku' => 'SKU-TEMPLATE-001',
            'name' => 'Nama Template',
            'item_type' => Item::TYPE_SINGLE,
            'status' => Item::STATUS_ACTIVE,
            'category_id' => 0,
            'description' => 'Tidak dipilih',
            'koli_qty' => 48,
        ]);

        $export = new ItemUpdatesTemplateExport(['name', 'status']);

        $this->assertSame(['sku', 'name', 'status'], $export->headings());
        $this->assertSame(['name', 'status'], $export->fields());
        $this->assertSame(
            ['SKU-TEMPLATE-001', 'Nama Template', Item::STATUS_ACTIVE],
            $export->collection()->first()
        );
        $this->assertNotContains('koli_qty', $export->headings());
    }

    public function test_template_endpoint_generates_excel_for_selected_fields(): void
    {
        Item::create([
            'sku' => 'SKU-TEMPLATE-ENDPOINT',
            'name' => 'Template Endpoint',
            'item_type' => Item::TYPE_SINGLE,
            'status' => Item::STATUS_ACTIVE,
            'category_id' => 0,
        ]);

        $response = $this->withoutMiddleware()->get(route('admin.masterdata.items.update-template', [
            'fields' => ['name', 'status'],
        ]));

        $response->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_template_endpoint_rejects_locked_field_selection(): void
    {
        $response = $this->withoutMiddleware()->getJson(route('admin.masterdata.items.update-template', [
            'fields' => ['name', 'koli_qty'],
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fields.1']);
    }

    public function test_custom_update_routes_require_item_update_permission(): void
    {
        foreach (['admin.masterdata.items.update-template', 'admin.masterdata.items.update-import'] as $routeName) {
            $this->assertSame('admin.masterdata.items.index', Permission::resolveBaseRoute($routeName));
            $this->assertSame('update', Permission::actionFromRoute($routeName));
        }
    }

    public function test_downloaded_template_can_be_imported_back_without_changing_locked_fields(): void
    {
        $item = Item::create([
            'sku' => 'SKU-ROUNDTRIP-001',
            'name' => 'Nama Roundtrip',
            'item_type' => Item::TYPE_SINGLE,
            'status' => Item::STATUS_ACTIVE,
            'category_id' => 0,
            'koli_qty' => 36,
        ]);

        $path = tempnam(sys_get_temp_dir(), 'item-update-');
        file_put_contents(
            $path,
            Excel::raw(new ItemUpdatesTemplateExport(['name']), ExcelFormat::XLSX)
        );

        try {
            $response = $this->withoutMiddleware()->postJson(route('admin.masterdata.items.update-import'), [
                'fields' => ['name'],
                'file' => new UploadedFile(
                    $path,
                    'items-update-name.xlsx',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    null,
                    true
                ),
            ]);
        } finally {
            @unlink($path);
        }

        $response->assertOk()
            ->assertJsonPath('processed', 1)
            ->assertJsonPath('updated', 0)
            ->assertJsonPath('unchanged', 1);
        $this->assertSame(36, $item->fresh()->koli_qty);
    }

    public function test_items_page_renders_update_excel_workflow(): void
    {
        $user = User::factory()->create();

        $response = $this->withoutMiddleware()
            ->actingAs($user)
            ->get(route('admin.masterdata.items.index'));

        $response->assertOk()
            ->assertSee('Update Data Items')
            ->assertSee('Pilih field yang akan diperbarui')
            ->assertSee('item_update_field_name', false)
            ->assertSee('Download Template')
            ->assertSee('SKU, tipe item, isi koli, data bundle, barcode, dan jumlah stok tidak dapat diubah');
    }
}
