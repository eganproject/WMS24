<?php

namespace Tests\Feature\Inbound;

use App\Http\Middleware\AuthorizeMenuPermission;
use App\Imports\InboundReturnsImport;
use App\Imports\InboundFormItemsImport;
use App\Models\InboundScanSession;
use App\Models\InboundTransaction;
use App\Models\Item;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InboundReturnWarehouseUnitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(AuthorizeMenuPermission::class);
    }

    public function test_return_form_exposes_explicit_destination_warehouse_and_input_unit_controls(): void
    {
        Warehouse::create(['code' => 'WH-RET-UI', 'name' => 'Gudang Retur UI', 'type' => 'return']);

        $this->actingAs(User::factory()->create(['is_active' => true]))
            ->get(route('admin.inbound.returns.index'))
            ->assertOk()
            ->assertSee('Gudang Tujuan')
            ->assertSee('id="flow_warehouse_id"', false)
            ->assertSee('id="flow_quick_input_unit"', false)
            ->assertSee('const enableInputUnitSelect = true;', false)
            ->assertSee('const requireExplicitWarehouseSelection = true;', false);
    }

    public function test_return_requires_a_valid_destination_warehouse(): void
    {
        $item = $this->createItem('RET-WH-VALIDATION', 12);

        $this->actingAs(User::factory()->create(['is_active' => true]))
            ->postJson(route('admin.inbound.returns.store'), [
                'transacted_at' => now()->format('Y-m-d H:i:s'),
                'items' => [[
                    'item_id' => $item->id,
                    'qty' => 3,
                    'input_unit' => 'pcs',
                ]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('warehouse_id');

        $this->assertDatabaseCount('inbound_transactions', 0);

        $this->actingAs(User::factory()->create(['is_active' => true]))
            ->postJson(route('admin.inbound.returns.import'), [
                'file' => UploadedFile::fake()->create('retur.xlsx', 10, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('warehouse_id');
    }

    public function test_pcs_return_scans_per_piece_and_posts_stock_to_selected_warehouse(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $scanner = $this->createScanner();
        $warehouse = Warehouse::where('code', 'GUDANG_DISPLAY')->firstOrFail();
        $otherWarehouse = Warehouse::where('code', 'GUDANG_BESAR')->firstOrFail();
        $item = $this->createItem('RET-PCS-001', 12);

        $this->actingAs($admin)
            ->postJson(route('admin.inbound.returns.store'), [
                'ref_no' => 'REF-RET-PCS-001',
                'warehouse_id' => $warehouse->id,
                'transacted_at' => now()->format('Y-m-d H:i:s'),
                'items' => [[
                    'item_id' => $item->id,
                    'qty' => 5,
                    'input_unit' => 'pcs',
                ]],
            ])
            ->assertOk();

        $transaction = InboundTransaction::firstOrFail();
        $this->assertSame($warehouse->id, $transaction->warehouse_id);
        $this->assertDatabaseHas('inbound_items', [
            'inbound_transaction_id' => $transaction->id,
            'item_id' => $item->id,
            'qty' => 5,
            'koli' => null,
            'input_unit' => 'pcs',
        ]);

        $this->actingAs($scanner)
            ->postJson(route('mobile.inbound-scan.open'), ['transaction_id' => $transaction->id])
            ->assertOk()
            ->assertJsonPath('transaction.items.0.input_unit', 'pcs')
            ->assertJsonPath('transaction.items.0.qty_per_koli', 1)
            ->assertJsonPath('transaction.items.0.expected_koli', 5)
            ->assertJsonPath('transaction.items.0.expected_qty', 5);

        $session = InboundScanSession::firstOrFail();
        for ($scan = 1; $scan <= 5; $scan++) {
            $this->actingAs($scanner)
                ->postJson(route('mobile.inbound-scan.scan-sku'), [
                    'session_id' => $session->id,
                    'code' => $item->sku,
                ])
                ->assertOk()
                ->assertJsonPath('transaction.summary.scanned_qty', $scan);
        }

        $this->actingAs($scanner)
            ->postJson(route('mobile.inbound-scan.complete'), ['session_id' => $session->id])
            ->assertOk()
            ->assertJsonPath('transaction.status', 'completed');

        $this->assertDatabaseHas('stock_mutations', [
            'source_type' => 'inbound',
            'source_id' => $transaction->id,
            'source_subtype' => 'return',
            'item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'qty' => 5,
        ]);
        $this->assertDatabaseHas('item_stocks', [
            'item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'stock' => 5,
        ]);
        $this->assertDatabaseMissing('item_stocks', [
            'item_id' => $item->id,
            'warehouse_id' => $otherWarehouse->id,
        ]);
        $this->assertDatabaseMissing('inbound_koli_units', [
            'inbound_transaction_id' => $transaction->id,
            'item_id' => $item->id,
        ]);
    }

    public function test_koli_return_keeps_master_pack_conversion(): void
    {
        $warehouse = Warehouse::create([
            'code' => 'WH-RET-KOLI',
            'name' => 'Gudang Retur Koli',
            'type' => 'return',
        ]);
        $item = $this->createItem('RET-KOLI-001', 12);

        $this->actingAs(User::factory()->create(['is_active' => true]))
            ->postJson(route('admin.inbound.returns.store'), [
                'warehouse_id' => $warehouse->id,
                'transacted_at' => now()->format('Y-m-d H:i:s'),
                'items' => [[
                    'item_id' => $item->id,
                    'qty' => 24,
                    'koli' => 2,
                    'input_unit' => 'koli',
                ]],
            ])
            ->assertOk();

        $this->assertDatabaseHas('inbound_items', [
            'item_id' => $item->id,
            'qty' => 24,
            'koli' => 2,
            'input_unit' => 'koli',
        ]);
    }

    public function test_pcs_is_limited_to_display_and_damaged_warehouses(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $mainWarehouse = Warehouse::where('code', 'GUDANG_BESAR')->firstOrFail();
        $damagedWarehouse = Warehouse::where('code', 'GUDANG_RUSAK')->firstOrFail();
        $item = $this->createItem('RET-PCS-WH-RULE', 12);
        $payload = [
            'transacted_at' => now()->format('Y-m-d H:i:s'),
            'items' => [[
                'item_id' => $item->id,
                'qty' => 5,
                'input_unit' => 'pcs',
            ]],
        ];

        $this->actingAs($admin)
            ->postJson(route('admin.inbound.returns.store'), $payload + ['warehouse_id' => $mainWarehouse->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('items.0.input_unit');

        $this->actingAs($admin)
            ->postJson(route('admin.inbound.returns.store'), $payload + ['warehouse_id' => $damagedWarehouse->id])
            ->assertOk();

        $this->assertDatabaseHas('inbound_items', [
            'item_id' => $item->id,
            'qty' => 5,
            'koli' => null,
            'input_unit' => 'pcs',
        ]);
    }

    public function test_main_warehouse_requires_qty_to_be_a_full_koli_multiple(): void
    {
        $mainWarehouse = Warehouse::where('code', 'GUDANG_BESAR')->firstOrFail();
        $item = $this->createItem('RET-MAIN-KOLI-RULE', 12);

        $this->actingAs(User::factory()->create(['is_active' => true]))
            ->postJson(route('admin.inbound.returns.store'), [
                'warehouse_id' => $mainWarehouse->id,
                'transacted_at' => now()->format('Y-m-d H:i:s'),
                'items' => [[
                    'item_id' => $item->id,
                    'qty' => 5,
                    'input_unit' => 'koli',
                ]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.qty', 'items.0.koli']);

        $this->assertDatabaseCount('inbound_transactions', 0);
    }

    public function test_return_import_only_accepts_pcs_when_destination_allows_it(): void
    {
        $item = $this->createItem('RET-IMPORT-PCS-RULE', 12);
        $rows = new Collection([new Collection([
            'sku' => $item->sku,
            'qty' => 5,
            'input_unit' => 'pcs',
            'ref_no' => 'RET-IMPORT-PCS',
        ])]);

        $allowedImport = new InboundReturnsImport(true);
        $allowedImport->collection($rows);
        $importedItem = collect($allowedImport->groups)->first()['items'][0];

        $this->assertSame('pcs', $importedItem['input_unit']);
        $this->assertSame(5, $importedItem['qty']);
        $this->assertNull($importedItem['koli']);

        $this->expectException(ValidationException::class);
        (new InboundReturnsImport(false))->collection($rows);
    }

    public function test_return_form_item_import_applies_the_same_pcs_warehouse_rule(): void
    {
        $item = $this->createItem('RET-FORM-IMPORT-PCS', 12);
        $rows = new Collection([new Collection([
            'sku' => $item->sku,
            'qty' => 5,
            'input_unit' => 'pcs',
        ])]);

        $allowedImport = new InboundFormItemsImport(true, true);
        $allowedImport->collection($rows);

        $this->assertSame('pcs', $allowedImport->items[0]['input_unit']);
        $this->assertSame(5, $allowedImport->items[0]['qty']);
        $this->assertNull($allowedImport->items[0]['koli']);

        $this->expectException(ValidationException::class);
        (new InboundFormItemsImport(true, false))->collection($rows);
    }

    private function createItem(string $sku, int $koliQty): Item
    {
        return Item::create([
            'sku' => $sku,
            'name' => 'Item '.$sku,
            'category_id' => 0,
            'koli_qty' => $koliQty,
        ]);
    }

    private function createScanner(): User
    {
        $role = Role::firstOrCreate(
            ['slug' => 'inbound-scan'],
            ['name' => 'INBOUND SCAN', 'description' => 'inbound-scan']
        );
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach($role);

        return $user;
    }
}
