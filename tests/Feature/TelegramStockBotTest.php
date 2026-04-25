<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemStock;
use App\Models\Kurir;
use App\Models\QcResiScan;
use App\Models\Resi;
use App\Models\ShipmentScanOut;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

    public function test_telegram_resi_command_replies_with_today_summary(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-25 10:00:00'));
        config([
            'services.telegram.bot_token' => 'TEST_TOKEN',
            'services.telegram.webhook_secret' => 'secret-token',
            'services.telegram.allowed_chat_ids' => [],
        ]);
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $user = User::factory()->create();
        $kurir = Kurir::create(['name' => 'JNE']);
        $today = '2026-04-25';
        $firstResi = $this->createResi($user, $kurir, 'ORD-001', 'RS001', $today);
        $secondResi = $this->createResi($user, $kurir, 'ORD-002', 'RS002', $today);
        $thirdResi = $this->createResi($user, $kurir, 'ORD-003', 'RS003', $today);
        $this->createResi($user, $kurir, 'ORD-004', 'RS004', $today, 'canceled');
        $this->createResi($user, $kurir, 'ORD-OLD', 'RSOLD', '2026-04-24');

        QcResiScan::create([
            'resi_id' => $firstResi->id,
            'scan_type' => 'resi',
            'scan_code' => $firstResi->no_resi,
            'status' => 'passed',
            'started_at' => now(),
            'completed_at' => now(),
            'scanned_by' => $user->id,
            'completed_by' => $user->id,
        ]);
        QcResiScan::create([
            'resi_id' => $secondResi->id,
            'scan_type' => 'resi',
            'scan_code' => $secondResi->no_resi,
            'status' => 'draft',
            'started_at' => now(),
            'scanned_by' => $user->id,
        ]);
        ShipmentScanOut::create([
            'resi_id' => $firstResi->id,
            'kurir_id' => $kurir->id,
            'scan_type' => 'resi',
            'scan_code' => $firstResi->no_resi,
            'scan_date' => $today,
            'scanned_at' => now(),
            'scanned_by' => $user->id,
        ]);

        $response = $this
            ->withHeader('X-Telegram-Bot-Api-Secret-Token', 'secret-token')
            ->postJson(route('telegram.webhook'), [
                'message' => [
                    'chat' => ['id' => 12345],
                    'text' => '/resi',
                ],
            ]);

        $response->assertOk()->assertJsonPath('ok', true);
        Http::assertSent(function ($request) use ($thirdResi) {
            $payload = $request->data();

            return $request->url() === 'https://api.telegram.org/botTEST_TOKEN/sendMessage'
                && $payload['chat_id'] === '12345'
                && str_contains($payload['text'], 'Ringkasan Resi Hari Ini')
                && str_contains($payload['text'], '2026-04-25')
                && str_contains($payload['text'], 'Resi aktif upload hari ini: 3')
                && str_contains($payload['text'], 'Cancel: 1')
                && str_contains($payload['text'], 'Scan out hari ini: 1')
                && str_contains($payload['text'], 'Belum scan out dari upload hari ini: 2')
                && str_contains($payload['text'], '- Menunggu QC: 1')
                && str_contains($payload['text'], '- QC berjalan: 1')
                && str_contains($payload['text'], '- Siap scan out: 0')
                && str_contains($payload['text'], '- Scan out selesai: 1')
                && str_contains($payload['text'], '- JNE: 3 resi, scan out 1, sisa 2');
        });

        Carbon::setTestNow();
        $this->assertTrue($thirdResi->exists);
    }

    private function createResi(
        User $user,
        Kurir $kurir,
        string $orderId,
        string $noResi,
        string $date,
        ?string $status = 'active'
    ): Resi {
        return Resi::create([
            'id_pesanan' => $orderId,
            'tanggal_pesanan' => $date,
            'tanggal_upload' => $date,
            'no_resi' => $noResi,
            'kurir_id' => $kurir->id,
            'status' => $status,
            'uploader_id' => $user->id,
        ]);
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
