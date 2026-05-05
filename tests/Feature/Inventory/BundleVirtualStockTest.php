<?php

namespace Tests\Feature\Inventory;

use App\Imports\ItemBundleImport;
use App\Models\DamagedGoodItem;
use App\Models\Item;
use App\Models\ItemBundleComponent;
use App\Models\ItemStock;
use App\Models\OutboundItem;
use App\Models\OutboundTransaction;
use App\Models\StockMutation;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\BundleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BundleVirtualStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_bundle_virtual_stock_uses_lowest_component_ratio(): void
    {
        $displayWarehouse = $this->createDisplayWarehouse();

        $componentA = Item::create([
            'sku' => 'CMP-A',
            'name' => 'Komponen A',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
        ]);
        $componentB = Item::create([
            'sku' => 'CMP-B',
            'name' => 'Komponen B',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
        ]);
        $bundle = Item::create([
            'sku' => 'BDL-001',
            'name' => 'Bundle 001',
            'item_type' => Item::TYPE_BUNDLE,
            'category_id' => 0,
        ]);

        ItemBundleComponent::create([
            'bundle_item_id' => $bundle->id,
            'component_item_id' => $componentA->id,
            'required_qty' => 2,
        ]);
        ItemBundleComponent::create([
            'bundle_item_id' => $bundle->id,
            'component_item_id' => $componentB->id,
            'required_qty' => 3,
        ]);

        ItemStock::create([
            'item_id' => $componentA->id,
            'warehouse_id' => $displayWarehouse->id,
            'stock' => 11,
        ]);
        ItemStock::create([
            'item_id' => $componentB->id,
            'warehouse_id' => $displayWarehouse->id,
            'stock' => 7,
        ]);

        $this->assertSame(2, BundleService::virtualAvailableQty($bundle->fresh(), $displayWarehouse->id));
    }

    public function test_bundle_component_normalization_can_merge_split_form_rows(): void
    {
        $normalized = BundleService::normalizeComponents([
            ['component_item_id' => 10],
            ['required_qty' => 2],
            ['component_item_id' => 10, 'required_qty' => 1],
            ['component_item_id' => 20],
            ['required_qty' => 4],
        ]);

        $this->assertSame([
            [
                'component_item_id' => 10,
                'required_qty' => 3,
            ],
            [
                'component_item_id' => 20,
                'required_qty' => 4,
            ],
        ], $normalized);
    }

    public function test_outbound_bundle_depletes_components_atomically_and_keeps_bundle_as_reference(): void
    {
        $displayWarehouse = $this->createDisplayWarehouse();
        $user = User::factory()->create();

        $componentA = Item::create([
            'sku' => 'CMP-OUT-A',
            'name' => 'Komponen Out A',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
        ]);
        $componentB = Item::create([
            'sku' => 'CMP-OUT-B',
            'name' => 'Komponen Out B',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
        ]);
        $bundle = Item::create([
            'sku' => 'BDL-OUT-01',
            'name' => 'Bundle Out 01',
            'item_type' => Item::TYPE_BUNDLE,
            'category_id' => 0,
        ]);

        ItemBundleComponent::insert([
            [
                'bundle_item_id' => $bundle->id,
                'component_item_id' => $componentA->id,
                'required_qty' => 2,
            ],
            [
                'bundle_item_id' => $bundle->id,
                'component_item_id' => $componentB->id,
                'required_qty' => 1,
            ],
        ]);

        ItemStock::insert([
            [
                'item_id' => $componentA->id,
                'warehouse_id' => $displayWarehouse->id,
                'stock' => 10,
            ],
            [
                'item_id' => $componentB->id,
                'warehouse_id' => $displayWarehouse->id,
                'stock' => 10,
            ],
        ]);

        $transaction = OutboundTransaction::create([
            'code' => 'OUT-BDL-001',
            'type' => 'manual',
            'warehouse_id' => $displayWarehouse->id,
            'transacted_at' => now(),
            'created_by' => $user->id,
            'status' => 'pending',
        ]);

        OutboundItem::create([
            'outbound_transaction_id' => $transaction->id,
            'item_id' => $bundle->id,
            'qty' => 3,
        ]);

        $this->withoutMiddleware();
        $this->completeManualOutboundQc($user, $transaction, $bundle->sku, 3)
            ->assertOk();

        $this->assertDatabaseHas('item_stocks', [
            'item_id' => $componentA->id,
            'warehouse_id' => $displayWarehouse->id,
            'stock' => 4,
        ]);
        $this->assertDatabaseHas('item_stocks', [
            'item_id' => $componentB->id,
            'warehouse_id' => $displayWarehouse->id,
            'stock' => 7,
        ]);

        $this->assertDatabaseHas('stock_mutations', [
            'item_id' => $componentA->id,
            'reference_item_id' => $bundle->id,
            'reference_sku' => $bundle->sku,
            'warehouse_id' => $displayWarehouse->id,
            'direction' => 'out',
            'qty' => 6,
            'source_type' => 'outbound',
            'source_id' => $transaction->id,
        ]);
        $this->assertDatabaseHas('stock_mutations', [
            'item_id' => $componentB->id,
            'reference_item_id' => $bundle->id,
            'reference_sku' => $bundle->sku,
            'warehouse_id' => $displayWarehouse->id,
            'direction' => 'out',
            'qty' => 3,
            'source_type' => 'outbound',
            'source_id' => $transaction->id,
        ]);
        $this->assertDatabaseMissing('stock_mutations', [
            'item_id' => $bundle->id,
            'source_type' => 'outbound',
            'source_id' => $transaction->id,
        ]);
    }

    public function test_outbound_bundle_fails_atomically_when_one_component_is_short(): void
    {
        $displayWarehouse = $this->createDisplayWarehouse();
        $user = User::factory()->create();

        $componentA = Item::create([
            'sku' => 'CMP-FAIL-A',
            'name' => 'Komponen Fail A',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
        ]);
        $componentB = Item::create([
            'sku' => 'CMP-FAIL-B',
            'name' => 'Komponen Fail B',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
        ]);
        $bundle = Item::create([
            'sku' => 'BDL-FAIL-01',
            'name' => 'Bundle Fail 01',
            'item_type' => Item::TYPE_BUNDLE,
            'category_id' => 0,
        ]);

        ItemBundleComponent::insert([
            [
                'bundle_item_id' => $bundle->id,
                'component_item_id' => $componentA->id,
                'required_qty' => 2,
            ],
            [
                'bundle_item_id' => $bundle->id,
                'component_item_id' => $componentB->id,
                'required_qty' => 1,
            ],
        ]);

        ItemStock::insert([
            [
                'item_id' => $componentA->id,
                'warehouse_id' => $displayWarehouse->id,
                'stock' => 10,
            ],
            [
                'item_id' => $componentB->id,
                'warehouse_id' => $displayWarehouse->id,
                'stock' => 2,
            ],
        ]);

        $transaction = OutboundTransaction::create([
            'code' => 'OUT-BDL-FAIL',
            'type' => 'manual',
            'warehouse_id' => $displayWarehouse->id,
            'transacted_at' => now(),
            'created_by' => $user->id,
            'status' => 'pending',
        ]);

        OutboundItem::create([
            'outbound_transaction_id' => $transaction->id,
            'item_id' => $bundle->id,
            'qty' => 3,
        ]);

        $this->withoutMiddleware();
        $this->actingAs($user)
            ->postJson(route('admin.outbound.manuals.approve', $transaction->id))
            ->assertStatus(422)
            ->assertJsonPath('errors.qty.0', 'Stok tidak mencukupi untuk SKU CMP-FAIL-B. Tersedia 2, dibutuhkan 3.');

        $this->assertDatabaseHas('item_stocks', [
            'item_id' => $componentA->id,
            'warehouse_id' => $displayWarehouse->id,
            'stock' => 10,
        ]);
        $this->assertDatabaseHas('item_stocks', [
            'item_id' => $componentB->id,
            'warehouse_id' => $displayWarehouse->id,
            'stock' => 2,
        ]);
        $this->assertSame(0, StockMutation::count());
    }

    public function test_inbound_store_rejects_bundle_item_for_physical_stock_flow(): void
    {
        $user = User::factory()->create();
        $this->createDefaultWarehouse();

        $bundle = Item::create([
            'sku' => 'BDL-INB-01',
            'name' => 'Bundle Inbound',
            'item_type' => Item::TYPE_BUNDLE,
            'category_id' => 0,
        ]);

        $this->withoutMiddleware();
        $this->actingAs($user)
            ->postJson(route('admin.inbound.manuals.store'), [
                'items' => [
                    [
                        'item_id' => $bundle->id,
                        'qty' => 1,
                        'reason_code' => DamagedGoodItem::REASON_OTHER,
                    ],
                ],
                'transacted_at' => now()->format('Y-m-d H:i'),
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.items.0', 'Bundle tidak bisa digunakan pada inbound karena tidak memiliki stok fisik. SKU: BDL-INB-01');
    }

    public function test_items_admin_page_renders_bundle_configuration_form(): void
    {
        $user = User::factory()->create();

        $this->withoutMiddleware();
        $this->actingAs($user)
            ->get(route('admin.masterdata.items.index'))
            ->assertOk()
            ->assertSee('Bundle / Virtual Stock')
            ->assertSee('Komponen Bundle');
    }

    public function test_bundle_import_creates_missing_bundle_sku(): void
    {
        $componentA = Item::create([
            'sku' => 'CMP-IMP-A',
            'name' => 'Komponen Import A',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
        ]);
        $componentB = Item::create([
            'sku' => 'CMP-IMP-B',
            'name' => 'Komponen Import B',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
        ]);

        $import = new ItemBundleImport();
        $import->collection(collect([
            collect([
                'bundle_sku' => 'BDL-IMP-01',
                'bundle_name' => 'Bundle Import Baru',
                'component_sku' => $componentA->sku,
                'required_qty' => 2,
            ]),
            collect([
                'bundle_sku' => 'BDL-IMP-01',
                'bundle_name' => 'Bundle Import Baru',
                'component_sku' => $componentB->sku,
                'required_qty' => 1,
            ]),
        ]));

        $bundle = Item::query()->where('sku', 'BDL-IMP-01')->first();
        $this->assertNotNull($bundle);
        $this->assertSame(Item::TYPE_BUNDLE, $bundle->item_type);
        $this->assertSame('Bundle Import Baru', $bundle->name);

        foreach ($import->groups as $group) {
            BundleService::syncComponents($group['bundle'], $group['components']);
        }

        $this->assertDatabaseHas('item_bundle_components', [
            'bundle_item_id' => $bundle->id,
            'component_item_id' => $componentA->id,
            'required_qty' => 2,
        ]);
        $this->assertDatabaseHas('item_bundle_components', [
            'bundle_item_id' => $bundle->id,
            'component_item_id' => $componentB->id,
            'required_qty' => 1,
        ]);
        $this->assertDatabaseMissing('item_stocks', [
            'item_id' => $bundle->id,
        ]);
    }

    public function test_bundle_import_rejects_decimal_required_qty(): void
    {
        Item::create([
            'sku' => 'CMP-IMP-QTY',
            'name' => 'Komponen Qty',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
        ]);

        $import = new ItemBundleImport();

        try {
            $import->collection(collect([
                collect([
                    'bundle_sku' => 'BDL-IMP-QTY',
                    'bundle_name' => 'Bundle Qty Invalid',
                    'component_sku' => 'CMP-IMP-QTY',
                    'required_qty' => 1.5,
                ]),
            ]));
            $this->fail('Import bundle seharusnya menolak required_qty desimal.');
        } catch (ValidationException $e) {
            $this->assertSame(
                'required_qty harus berupa angka bulat minimal 1.',
                $e->errors()['required_qty'][0] ?? ''
            );
        }

        $this->assertDatabaseMissing('items', [
            'sku' => 'BDL-IMP-QTY',
        ]);
    }

    public function test_damaged_goods_store_rejects_bundle_item(): void
    {
        $user = User::factory()->create();
        $displayWarehouse = $this->createDisplayWarehouse();
        Warehouse::firstOrCreate(['code' => 'GUDANG_RUSAK'], ['name' => 'Gudang Rusak']);

        $bundle = Item::create([
            'sku' => 'BDL-DMG-01',
            'name' => 'Bundle Rusak',
            'item_type' => Item::TYPE_BUNDLE,
            'category_id' => 0,
        ]);

        $this->withoutMiddleware();
        $response = $this->actingAs($user)
            ->postJson(route('admin.inventory.damaged-goods.store'), [
                'source_type' => 'warehouse',
                'source_warehouse_id' => $displayWarehouse->id,
                'items' => [
                    [
                        'item_id' => $bundle->id,
                        'qty' => 1,
                        'reason_code' => DamagedGoodItem::REASON_OTHER,
                    ],
                ],
                'transacted_at' => now()->format('Y-m-d H:i'),
            ]);

        $response->assertStatus(422);
        $this->assertStringContainsString(
            'Bundle tidak bisa digunakan pada intake barang rusak karena tidak memiliki stok fisik. SKU: BDL-DMG-01',
            $response->getContent()
        );
    }

    public function test_rework_recipe_store_rejects_bundle_item_in_bom(): void
    {
        $user = User::factory()->create();
        $defaultWarehouse = $this->createDefaultWarehouse();

        $bundle = Item::create([
            'sku' => 'BDL-RWR-01',
            'name' => 'Bundle Rework',
            'item_type' => Item::TYPE_BUNDLE,
            'category_id' => 0,
        ]);
        $single = Item::create([
            'sku' => 'SGL-RWR-01',
            'name' => 'Single Rework',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
        ]);

        $this->withoutMiddleware();
        $this->actingAs($user)
            ->postJson(route('admin.inventory.rework-recipes.store'), [
                'name' => 'Recipe Bundle Invalid',
                'target_warehouse_id' => $defaultWarehouse->id,
                'input_items' => [
                    [
                        'item_id' => $bundle->id,
                        'qty' => 1,
                    ],
                ],
                'output_items' => [
                    [
                        'item_id' => $single->id,
                        'qty' => 1,
                    ],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.input_items.0', 'Bundle tidak bisa digunakan sebagai input resep rework karena tidak memiliki stok fisik. SKU: BDL-RWR-01');
    }

    public function test_stock_mutation_detail_shows_bundle_reference_for_component_depletion(): void
    {
        $displayWarehouse = $this->createDisplayWarehouse();
        $user = User::factory()->create();

        $component = Item::create([
            'sku' => 'CMP-MUT-01',
            'name' => 'Komponen Mutasi',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
        ]);
        $bundle = Item::create([
            'sku' => 'BDL-MUT-01',
            'name' => 'Bundle Mutasi',
            'item_type' => Item::TYPE_BUNDLE,
            'category_id' => 0,
        ]);

        ItemBundleComponent::create([
            'bundle_item_id' => $bundle->id,
            'component_item_id' => $component->id,
            'required_qty' => 2,
        ]);

        ItemStock::create([
            'item_id' => $component->id,
            'warehouse_id' => $displayWarehouse->id,
            'stock' => 10,
        ]);

        $transaction = OutboundTransaction::create([
            'code' => 'OUT-MUT-001',
            'type' => 'manual',
            'warehouse_id' => $displayWarehouse->id,
            'transacted_at' => now(),
            'created_by' => $user->id,
            'status' => 'pending',
        ]);

        OutboundItem::create([
            'outbound_transaction_id' => $transaction->id,
            'item_id' => $bundle->id,
            'qty' => 2,
        ]);

        $this->withoutMiddleware();
        $this->completeManualOutboundQc($user, $transaction, $bundle->sku, 2)
            ->assertOk();

        $mutation = StockMutation::query()->where('reference_item_id', $bundle->id)->firstOrFail();

        $this->actingAs($user)
            ->getJson(route('admin.inventory.stock-mutations.show', $mutation->id))
            ->assertOk()
            ->assertJsonPath('mutation.item', 'CMP-MUT-01 - Komponen Mutasi | Ref Bundle: BDL-MUT-01');
    }

    private function createDefaultWarehouse(): Warehouse
    {
        return Warehouse::firstOrCreate(
            ['code' => 'GUDANG_BESAR'],
            ['name' => 'Gudang Besar']
        );
    }

    private function createDisplayWarehouse(): Warehouse
    {
        return Warehouse::firstOrCreate(
            ['code' => 'GUDANG_DISPLAY'],
            ['name' => 'Gudang Display']
        );
    }

    private function completeManualOutboundQc(User $user, OutboundTransaction $transaction, string $sku, int $qty)
    {
        $this->actingAs($user)
            ->postJson(route('admin.outbound.manuals.approve', $transaction->id))
            ->assertOk();

        $openResponse = $this->actingAs($user)
            ->postJson(route('admin.outbound.manual-qc.open'), [
                'transaction_id' => $transaction->id,
            ])
            ->assertOk();

        $sessionId = $openResponse->json('transaction.session.id');

        $this->actingAs($user)
            ->postJson(route('admin.outbound.manual-qc.scan-sku'), [
                'session_id' => $sessionId,
                'code' => $sku,
                'qty' => $qty,
            ])
            ->assertOk();

        return $this->actingAs($user)
            ->postJson(route('admin.outbound.manual-qc.complete'), [
                'session_id' => $sessionId,
            ]);
    }
}
