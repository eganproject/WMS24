<?php

namespace Tests\Feature\Inbound;

use App\Http\Middleware\AuthorizeMenuPermission;
use App\Models\InboundScanSession;
use App\Models\InboundTransaction;
use App\Models\Item;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InboundScanFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_inbound_receipt_posts_stock_only_after_scan_complete(): void
    {
        $this->withoutMiddleware(AuthorizeMenuPermission::class);

        $admin = User::factory()->create();
        $scanner = $this->createUserWithRole('inbound-scan');
        $warehouse = Warehouse::create([
            'code' => 'GUDANG_BESAR',
            'name' => 'Gudang Besar',
        ]);
        $item = Item::create([
            'sku' => 'SKU-IN-001',
            'name' => 'Inbound Item',
            'category_id' => 0,
            'koli_qty' => 10,
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.inbound.receipts.store'), [
                'ref_no' => 'REF-IN-001',
                'surat_jalan_no' => 'SJ-IN-001',
                'surat_jalan_at' => now()->toDateString(),
                'transacted_at' => now()->format('Y-m-d H:i:s'),
                'items' => [
                    [
                        'item_id' => $item->id,
                        'qty' => 20,
                    ],
                ],
            ])
            ->assertOk();

        $transaction = InboundTransaction::firstOrFail();

        $this->assertSame('pending_scan', $transaction->status);
        $this->assertSame('SJ-IN-001', $transaction->surat_jalan_no);
        $this->assertDatabaseMissing('stock_mutations', [
            'source_type' => 'inbound',
            'source_id' => $transaction->id,
        ]);
        $this->assertDatabaseMissing('item_stocks', [
            'item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
        ]);

        $this->actingAs($scanner)
            ->postJson(route('picker.inbound-scan.open'), [
                'transaction_id' => $transaction->id,
            ])
            ->assertOk()
            ->assertJsonPath('transaction.status', 'scanning')
            ->assertJsonPath('transaction.summary.expected_koli', 2)
            ->assertJsonPath('transaction.items.0.qty_per_koli', 10);

        $session = InboundScanSession::firstOrFail();

        $this->actingAs($scanner)
            ->postJson(route('picker.inbound-scan.scan-sku'), [
                'session_id' => $session->id,
                'code' => 'SKU-IN-001',
            ])
            ->assertOk()
            ->assertJsonPath('transaction.summary.scanned_koli', 1)
            ->assertJsonPath('transaction.summary.scanned_qty', 10);

        $this->actingAs($scanner)
            ->postJson(route('picker.inbound-scan.scan-sku'), [
                'session_id' => $session->id,
                'code' => 'SKU-IN-001',
            ])
            ->assertOk()
            ->assertJsonPath('transaction.summary.scanned_koli', 2)
            ->assertJsonPath('transaction.summary.scanned_qty', 20);

        $this->actingAs($scanner)
            ->postJson(route('picker.inbound-scan.scan-sku'), [
                'session_id' => $session->id,
                'code' => 'SKU-IN-001',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Jumlah scan koli untuk SKU ini sudah penuh.');

        $this->actingAs($scanner)
            ->postJson(route('picker.inbound-scan.complete'), [
                'session_id' => $session->id,
            ])
            ->assertOk()
            ->assertJsonPath('transaction.status', 'completed');

        $transaction->refresh();
        $session->refresh();

        $this->assertSame('completed', $transaction->status);
        $this->assertSame($scanner->id, $transaction->approved_by);
        $this->assertSame($scanner->id, $session->completed_by);
        $this->assertDatabaseHas('stock_mutations', [
            'source_type' => 'inbound',
            'source_id' => $transaction->id,
            'source_subtype' => 'receipt',
            'item_id' => $item->id,
            'qty' => 20,
            'warehouse_id' => $warehouse->id,
        ]);
        $this->assertDatabaseHas('item_stocks', [
            'item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'stock' => 20,
        ]);
    }

    public function test_inbound_receipt_cannot_be_updated_after_scanning_starts(): void
    {
        $this->withoutMiddleware(AuthorizeMenuPermission::class);

        $admin = User::factory()->create();
        $scanner = $this->createUserWithRole('inbound-scan');
        Warehouse::create([
            'code' => 'GUDANG_BESAR',
            'name' => 'Gudang Besar',
        ]);
        $item = Item::create([
            'sku' => 'SKU-IN-002',
            'name' => 'Inbound Item 2',
            'category_id' => 0,
            'koli_qty' => 5,
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.inbound.receipts.store'), [
                'ref_no' => 'REF-IN-002',
                'transacted_at' => now()->format('Y-m-d H:i:s'),
                'items' => [
                    [
                        'item_id' => $item->id,
                        'qty' => 10,
                    ],
                ],
            ])
            ->assertOk();

        $transaction = InboundTransaction::firstOrFail();

        $this->actingAs($scanner)
            ->postJson(route('picker.inbound-scan.open'), [
                'transaction_id' => $transaction->id,
            ])
            ->assertOk()
            ->assertJsonPath('transaction.status', 'scanning');

        $this->actingAs($admin)
            ->putJson(route('admin.inbound.receipts.update', $transaction->id), [
                'ref_no' => 'REF-IN-002-EDIT',
                'transacted_at' => now()->format('Y-m-d H:i:s'),
                'items' => [
                    [
                        'item_id' => $item->id,
                        'qty' => 15,
                        'koli' => 3,
                    ],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Inbound yang sudah mulai discan tidak bisa diubah.');
    }

    private function createUserWithRole(string $slug): User
    {
        $role = Role::firstOrCreate(
            ['slug' => $slug],
            [
                'name' => strtoupper(str_replace('-', ' ', $slug)),
                'description' => $slug,
            ]
        );

        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }
}
