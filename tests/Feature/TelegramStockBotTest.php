<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemStock;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramStockBotTest extends TestCase
{
    use RefreshDatabase;

    public function test_telegram_stock_command_replies_with_item_stock_by_warehouse(): void
    {
        config([
            'services.telegram.bot_token' => 'TEST_TOKEN',
            'services.telegram.webhook_secret' => 'secret-token',
            'services.telegram.allowed_chat_ids' => [],
        ]);
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        [$mainWarehouse, $displayWarehouse, $damagedWarehouse] = $this->createWarehouseFixtures();
        $item = Item::create([
            'sku' => 'ADA1',
            'name' => 'Adaptor A1',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
        ]);
        ItemStock::create(['item_id' => $item->id, 'warehouse_id' => $mainWarehouse->id, 'stock' => 10]);
        ItemStock::create(['item_id' => $item->id, 'warehouse_id' => $displayWarehouse->id, 'stock' => 2]);
        ItemStock::create(['item_id' => $item->id, 'warehouse_id' => $damagedWarehouse->id, 'stock' => 1]);

        $response = $this
            ->withHeader('X-Telegram-Bot-Api-Secret-Token', 'secret-token')
            ->postJson(route('telegram.webhook'), [
                'message' => [
                    'chat' => ['id' => 12345],
                    'text' => '/stok ADA1',
                ],
            ]);

        $response->assertOk()->assertJsonPath('ok', true);
        Http::assertSent(function ($request) {
            $payload = $request->data();

            return $request->url() === 'https://api.telegram.org/botTEST_TOKEN/sendMessage'
                && $payload['chat_id'] === '12345'
                && str_contains($payload['text'], 'ADA1 - Adaptor A1')
                && str_contains($payload['text'], 'Gudang Besar: 10 pcs')
                && str_contains($payload['text'], 'Gudang Display: 2 pcs')
                && str_contains($payload['text'], 'Gudang Rusak: 1 pcs')
                && str_contains($payload['text'], 'Total: 13 pcs');
        });
    }

    public function test_telegram_webhook_rejects_invalid_secret_token(): void
    {
        config([
            'services.telegram.bot_token' => 'TEST_TOKEN',
            'services.telegram.webhook_secret' => 'secret-token',
        ]);
        Http::fake();

        $response = $this
            ->withHeader('X-Telegram-Bot-Api-Secret-Token', 'wrong-token')
            ->postJson(route('telegram.webhook'), [
                'message' => [
                    'chat' => ['id' => 12345],
                    'text' => '/stok ADA1',
                ],
            ]);

        $response->assertUnauthorized();
        Http::assertNothingSent();
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
