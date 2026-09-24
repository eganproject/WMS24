<?php

namespace Tests\Feature\Admin;

use App\Models\Channel;
use App\Models\Item;
use App\Models\Resi;
use App\Models\Store;
use App\Models\User;
use App\Support\Permission;
use Database\Seeders\MenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ResiStoreChannelTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_creates_or_reuses_store_and_channel_and_keeps_them_optional(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $existingStore = Store::create(['name' => 'Cassa Acrylic']);
        $existingChannel = Channel::create(['name' => 'Shopee']);

        foreach (['SKU-SC-001', 'SKU-SC-002', 'SKU-SC-003'] as $sku) {
            Item::create([
                'sku' => $sku,
                'name' => $sku,
                'item_type' => Item::TYPE_SINGLE,
                'category_id' => 0,
                'safety_stock' => 0,
            ]);
        }

        $file = $this->makeExcelUpload([
            ['Tanggal Pembuatan', 'ID Pesanan', 'SKU', 'Jumlah', 'Kurir', 'AWB/No. Tracking', 'Nama Toko', 'Channel'],
            ['26-08-2026 08:11', 'ORD-SC-001', 'SKU-SC-001', 1, 'SPX Hemat', 'AWB-SC-001', '  cassa   acrylic ', 'shopee'],
            ['2026-09-24', 'ORD-SC-002', 'SKU-SC-002', 2, 'JNE', 'AWB-SC-002', 'Toko Baru', 'TikTok Shop'],
        ]);

        $this->actingAs($user)
            ->withoutMiddleware()
            ->post(route('admin.inventory.resi-import.import'), ['file' => $file], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('resis', 2);

        $firstResi = Resi::where('id_pesanan', 'ORD-SC-001')->firstOrFail();
        $secondResi = Resi::where('id_pesanan', 'ORD-SC-002')->firstOrFail();

        $this->assertSame($existingStore->id, $firstResi->store_id);
        $this->assertSame($existingChannel->id, $firstResi->channel_id);
        $this->assertSame('Toko Baru', $secondResi->store?->name);
        $this->assertSame('TikTok Shop', $secondResi->channel?->name);
        $this->assertSame(2, Store::count());
        $this->assertSame(2, Channel::count());

        $this->actingAs($user)
            ->withoutMiddleware()
            ->getJson(route('admin.inventory.resi-import.data', [
                'date_from' => now()->toDateString(),
                'date_to' => now()->toDateString(),
                'q' => 'TikTok Shop',
            ]))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonFragment([
                'nama_toko' => 'Toko Baru',
                'channel' => 'TikTok Shop',
            ]);

        $legacyFile = $this->makeExcelUpload([
            ['ID Pesanan', 'SKU', 'Jumlah', 'Tanggal Pembuatan'],
            ['ORD-SC-001', 'SKU-SC-001', 1, '2026-09-24'],
            ['ORD-SC-003', 'SKU-SC-003', 1, '2026-09-24'],
        ]);

        $this->actingAs($user)
            ->withoutMiddleware()
            ->post(route('admin.inventory.resi-import.import'), ['file' => $legacyFile], ['Accept' => 'application/json'])
            ->assertOk();

        $legacyResi = Resi::where('id_pesanan', 'ORD-SC-003')->firstOrFail();
        $this->assertSame($existingStore->id, $firstResi->fresh()->store_id);
        $this->assertSame($existingChannel->id, $firstResi->fresh()->channel_id);
        $this->assertNull($legacyResi->store_id);
        $this->assertNull($legacyResi->channel_id);
        $this->assertSame(2, Store::count());
        $this->assertSame(2, Channel::count());
    }

    public function test_master_page_contains_store_and_channel_tabs_and_channel_crud_works(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->assertSame(
            'admin.masterdata.stores.index',
            Permission::resolveBaseRoute('admin.masterdata.stores.channels.destroy')
        );

        $this->actingAs($user)
            ->get(route('admin.masterdata.stores.index'))
            ->assertOk()
            ->assertSee('Master Data Toko', false)
            ->assertSee('id="tab_toko"', false)
            ->assertSee('id="tab_channel"', false);

        $this->actingAs($user)
            ->postJson(route('admin.masterdata.stores.channels.store'), ['name' => '  Tokopedia  '])
            ->assertOk()
            ->assertJsonPath('channel.name', 'Tokopedia');

        $channel = Channel::where('name', 'Tokopedia')->firstOrFail();

        $this->actingAs($user)
            ->putJson(route('admin.masterdata.stores.channels.update', $channel), ['name' => 'Tokopedia Official'])
            ->assertOk();

        $resi = Resi::create([
            'id_pesanan' => 'ORD-CHANNEL-DELETE',
            'tanggal_pesanan' => '2026-09-24',
            'tanggal_upload' => '2026-09-24',
            'channel_id' => $channel->id,
            'uploader_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->deleteJson(route('admin.masterdata.stores.channels.destroy', $channel))
            ->assertOk();

        $this->assertModelMissing($channel);
        $this->assertNull($resi->fresh()->channel_id);
    }

    public function test_menu_seeder_preserves_existing_menu_and_permission_customizations(): void
    {
        DB::table('roles')->insert([
            'name' => 'Administrator',
            'slug' => 'admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->seed(MenuSeeder::class);

        $roleId = DB::table('roles')->where('slug', 'admin')->value('id');
        $menuId = DB::table('menus')->where('slug', 'stores')->value('id');

        DB::table('menus')->where('id', $menuId)->update([
            'name' => 'Nama Menu Custom',
            'route' => 'custom.route',
            'is_active' => false,
        ]);
        DB::table('permission_menu')
            ->where('role_id', $roleId)
            ->where('menu_id', $menuId)
            ->update([
                'can_view' => false,
                'can_create' => false,
                'can_update' => false,
                'can_delete' => false,
            ]);

        $this->seed(MenuSeeder::class);

        $this->assertDatabaseHas('menus', [
            'id' => $menuId,
            'name' => 'Nama Menu Custom',
            'route' => 'custom.route',
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('permission_menu', [
            'role_id' => $roleId,
            'menu_id' => $menuId,
            'can_view' => false,
            'can_create' => false,
            'can_update' => false,
            'can_delete' => false,
        ]);
    }

    private function makeExcelUpload(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($rows as $rowIndex => $row) {
            foreach (array_values($row) as $columnIndex => $value) {
                $sheet->setCellValueByColumnAndRow($columnIndex + 1, $rowIndex + 1, $value);
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'resi-store-channel-');
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return new UploadedFile(
            $path,
            'resi-store-channel.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }
}
