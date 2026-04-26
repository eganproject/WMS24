<?php

namespace Tests\Feature;

use App\Models\CustomerReturn;
use App\Models\CustomerReturnItem;
use App\Models\DamagedGood;
use App\Models\DamagedGoodItem;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\Kurir;
use App\Models\PickingList;
use App\Models\PickingListException;
use App\Models\QcResiScan;
use App\Models\Resi;
use App\Models\ResiDetail;
use App\Models\ShipmentScanOut;
use App\Models\StockMutation;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\TelegramBotService;
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

    public function test_new_telegram_operational_commands_reply_with_wms_data(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-26 09:00:00'));

        [$mainWarehouse] = $this->createWarehouseFixtures();
        $user = User::factory()->create();
        $kurir = Kurir::create(['name' => 'JNT']);
        $item = Item::create([
            'sku' => 'SKU-LOW',
            'name' => 'Barang Low Stock',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
            'address' => 'A-01-02',
            'safety_stock' => 10,
        ]);
        ItemStock::create([
            'item_id' => $item->id,
            'warehouse_id' => $mainWarehouse->id,
            'stock' => 3,
            'safety_stock' => 10,
        ]);
        StockMutation::create([
            'item_id' => $item->id,
            'reference_item_id' => $item->id,
            'reference_sku' => $item->sku,
            'warehouse_id' => $mainWarehouse->id,
            'direction' => 'out',
            'qty' => 2,
            'source_type' => 'manual',
            'source_id' => 1,
            'source_code' => 'ADJ-1',
            'occurred_at' => now(),
            'created_by' => $user->id,
        ]);

        $resi = $this->createResi($user, $kurir, 'ORD-NEW', 'RSNEW', '2026-04-26');
        ResiDetail::create(['resi_id' => $resi->id, 'sku' => $item->sku, 'qty' => 2]);
        QcResiScan::create([
            'resi_id' => $resi->id,
            'scan_type' => 'resi',
            'scan_code' => $resi->no_resi,
            'status' => 'passed',
            'started_at' => now(),
            'completed_at' => now(),
            'scanned_by' => $user->id,
            'completed_by' => $user->id,
        ]);
        ShipmentScanOut::create([
            'resi_id' => $resi->id,
            'kurir_id' => $kurir->id,
            'scan_type' => 'resi',
            'scan_code' => $resi->no_resi,
            'scan_date' => '2026-04-26',
            'scanned_at' => now(),
            'scanned_by' => $user->id,
        ]);
        PickingList::create(['list_date' => '2026-04-26', 'sku' => $item->sku, 'qty' => 5, 'remaining_qty' => 2]);
        PickingListException::create(['list_date' => '2026-04-26', 'sku' => $item->sku, 'qty' => 1]);
        $return = CustomerReturn::create([
            'code' => 'RET-1',
            'resi_id' => $resi->id,
            'resi_no' => $resi->no_resi,
            'order_ref' => $resi->id_pesanan,
            'received_at' => now(),
            'status' => CustomerReturn::STATUS_COMPLETED,
            'finalized_at' => now(),
            'created_by' => $user->id,
        ]);
        CustomerReturnItem::create([
            'customer_return_id' => $return->id,
            'item_id' => $item->id,
            'expected_qty' => 2,
            'received_qty' => 2,
            'good_qty' => 1,
            'damaged_qty' => 1,
        ]);
        $damaged = DamagedGood::create([
            'code' => 'DMG-1',
            'source_type' => DamagedGood::SOURCE_CUSTOMER_RETURN,
            'source_warehouse_id' => $mainWarehouse->id,
            'source_ref' => 'RET-1',
            'transacted_at' => now(),
            'status' => 'approved',
            'created_by' => $user->id,
        ]);
        DamagedGoodItem::create([
            'damaged_good_id' => $damaged->id,
            'item_id' => $item->id,
            'qty' => 1,
            'reason_code' => DamagedGoodItem::REASON_CUSTOMER_RETURN,
        ]);

        $bot = app(TelegramBotService::class);

        $this->assertStringContainsString('Status operasional: Scan Out Selesai', $bot->replyForText('/cekresi RSNEW'));
        $this->assertStringContainsString('SKU-LOW | Gudang Besar: 3/10 pcs', $bot->replyForText('/lowstock'));
        $this->assertStringContainsString('Lokasi: A-01-02', $bot->replyForText('/lokasi SKU-LOW'));
        $this->assertStringContainsString('ADJ-1', $bot->replyForText('/mutasi SKU-LOW'));
        $this->assertStringContainsString('QC passed: 1', $bot->replyForText('/today'));
        $this->assertStringContainsString('Passed: 1', $bot->replyForText('/qc'));
        $this->assertStringContainsString('Total scan out: 1', $bot->replyForText('/scanout'));
        $this->assertStringContainsString('Sisa qty: 2', $bot->replyForText('/picking'));
        $this->assertStringContainsString('Completed: 1', $bot->replyForText('/return'));
        $this->assertStringContainsString('Total qty rusak: 1', $bot->replyForText('/damaged'));

        Carbon::setTestNow();
    }

    public function test_telegram_info_command_replies_with_usage_guide(): void
    {
        $bot = app(TelegramBotService::class);

        $reply = $bot->replyForText('/info');

        $this->assertStringContainsString('Panduan Telegram Bot WMS', $reply);
        $this->assertStringContainsString('/stok SKU - cek stok SKU per gudang', $reply);
        $this->assertStringContainsString('/cekresi NO_RESI - detail status resi', $reply);
        $this->assertStringContainsString('/today - dashboard operasional hari ini', $reply);
        $this->assertStringContainsString('/damaged - ringkasan barang rusak hari ini', $reply);
        $this->assertStringContainsString('Ketik /info', $bot->replyForText('/tidak_ada'));
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
