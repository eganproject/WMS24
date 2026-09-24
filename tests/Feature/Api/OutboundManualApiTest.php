<?php

namespace Tests\Feature\Api;

use App\Models\Item;
use App\Models\ItemStock;
use App\Models\OutboundTransaction;
use App\Models\StockApiAllowedIp;
use App\Models\Warehouse;
use App\Support\OutboundManualQcStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OutboundManualApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'outbound_manual_api.enabled' => true,
            'outbound_manual_api.token' => 'outbound-api-test-token',
            'outbound_manual_api.created_by_user_id' => null,
            'stock_api.token' => 'read-only-stock-token',
            'inventory.default_warehouse_code' => 'GUDANG_BESAR',
            'inventory.display_warehouse_code' => 'GUDANG_DISPLAY',
        ]);

        StockApiAllowedIp::create([
            'ip_address' => '127.0.0.1',
            'label' => 'PHPUnit',
            'is_active' => true,
        ]);
    }

    public function test_it_creates_manual_outbound_using_warehouse_code_and_sku(): void
    {
        $warehouse = $this->createWarehouse('GUDANG_DISPLAY');
        $item = $this->createItem('SKU-API-OUT-1');
        $this->setStock($warehouse, $item, 10);

        $response = $this->postJson('/api/v1/outbound/manuals', $this->payload(), $this->headers());

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.idempotent_replay', false)
            ->assertJsonPath('data.external_id', 'OMS-TEST-0001')
            ->assertJsonPath('data.status', OutboundManualQcStatus::PENDING_QC)
            ->assertJsonPath('data.warehouse.code', 'GUDANG_DISPLAY')
            ->assertJsonPath('data.items.0.sku', 'SKU-API-OUT-1')
            ->assertJsonPath('data.items.0.qty', 3);

        $transaction = OutboundTransaction::query()->firstOrFail();
        $this->assertSame('manual', $transaction->type);
        $this->assertSame($warehouse->id, (int) $transaction->warehouse_id);
        $this->assertNull($transaction->created_by);
        $this->assertDatabaseHas('outbound_items', [
            'outbound_transaction_id' => $transaction->id,
            'item_id' => $item->id,
            'qty' => 3,
        ]);
        $this->assertDatabaseHas('item_stocks', [
            'item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'stock' => 10,
        ]);
    }

    public function test_same_external_id_and_payload_is_idempotent_but_different_payload_conflicts(): void
    {
        $warehouse = $this->createWarehouse('GUDANG_DISPLAY');
        $item = $this->createItem('SKU-API-OUT-1');
        $this->setStock($warehouse, $item, 10);

        $this->postJson('/api/v1/outbound/manuals', $this->payload(), $this->headers())
            ->assertCreated();

        $this->postJson('/api/v1/outbound/manuals', $this->payload(), $this->headers())
            ->assertOk()
            ->assertJsonPath('meta.idempotent_replay', true);

        $changed = $this->payload();
        $changed['items'][0]['qty'] = 4;

        $this->postJson('/api/v1/outbound/manuals', $changed, $this->headers())
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'IDEMPOTENCY_CONFLICT');

        $this->assertDatabaseCount('outbound_transactions', 1);
        $this->assertDatabaseCount('outbound_items', 1);
    }

    public function test_it_rejects_insufficient_stock(): void
    {
        $warehouse = $this->createWarehouse('GUDANG_DISPLAY');
        $item = $this->createItem('SKU-API-OUT-1');
        $this->setStock($warehouse, $item, 2);

        $this->postJson('/api/v1/outbound/manuals', $this->payload(), $this->headers())
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath(
                'error.details.qty.0',
                'Stok tidak mencukupi untuk SKU SKU-API-OUT-1. Tersedia 2, dibutuhkan 3.',
            );

        $this->assertDatabaseCount('outbound_transactions', 0);
    }

    public function test_main_warehouse_requires_valid_koli(): void
    {
        $warehouse = $this->createWarehouse('GUDANG_BESAR', 'main');
        $item = $this->createItem('SKU-API-OUT-1', 6);
        $this->setStock($warehouse, $item, 30);

        $payload = $this->payload('GUDANG_BESAR');

        $this->postJson('/api/v1/outbound/manuals', $payload, $this->headers())
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonFragment([
                'items.0.koli' => ['Koli wajib diisi untuk outbound manual dari Gudang Besar.'],
            ]);

        $payload['items'][0] = [
            'sku' => 'SKU-API-OUT-1',
            'qty' => 12,
            'koli' => 2,
        ];

        $this->postJson('/api/v1/outbound/manuals', $payload, $this->headers())
            ->assertCreated()
            ->assertJsonPath('data.items.0.qty', 12);
    }

    public function test_it_uses_existing_api_authentication_and_ip_allowlist(): void
    {
        $this->postJson('/api/v1/outbound/manuals', [])
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'UNAUTHORIZED');

        $this->postJson('/api/v1/outbound/manuals', [], [
            'Authorization' => 'Bearer read-only-stock-token',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'UNAUTHORIZED');

        StockApiAllowedIp::query()->delete();

        $this->postJson('/api/v1/outbound/manuals', [], $this->headers())
            ->assertForbidden()
            ->assertJsonPath('error.code', 'IP_NOT_ALLOWED');
    }

    private function payload(string $warehouseCode = 'GUDANG_DISPLAY'): array
    {
        return [
            'external_id' => 'OMS-TEST-0001',
            'warehouse_code' => $warehouseCode,
            'transacted_at' => '2026-09-24T09:30:00+07:00',
            'ref_no' => 'ORDER-TEST-0001',
            'recipient_name' => 'Budi Penerima',
            'recipient_phone' => '08123456789',
            'recipient_address' => 'Jl. API No. 1',
            'items' => [
                [
                    'sku' => 'SKU-API-OUT-1',
                    'qty' => 3,
                ],
            ],
        ];
    }

    private function headers(): array
    {
        return [
            'Authorization' => 'Bearer outbound-api-test-token',
        ];
    }

    private function createWarehouse(string $code, string $type = 'display'): Warehouse
    {
        return Warehouse::query()->updateOrCreate([
            'code' => $code,
        ], [
            'name' => $code,
            'type' => $type,
        ]);
    }

    private function createItem(string $sku, int $koliQty = 0): Item
    {
        return Item::create([
            'sku' => $sku,
            'name' => $sku,
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
            'koli_qty' => $koliQty,
        ]);
    }

    private function setStock(Warehouse $warehouse, Item $item, int $qty): void
    {
        ItemStock::create([
            'warehouse_id' => $warehouse->id,
            'item_id' => $item->id,
            'stock' => $qty,
        ]);
    }
}
