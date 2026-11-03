<?php

namespace Tests\Feature\Admin;

use App\Models\Item;
use App\Models\ItemStock;
use App\Models\OutboundTransaction;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\DeliveryNoteNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class OutboundManualDeliveryNoteNumberTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_generates_short_sequential_numbers_per_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-03 10:00:00', 'Asia/Jakarta'));
        $this->assertSame('SJM-261103-0001', DeliveryNoteNumber::nextManual());
        $this->assertSame('SJM-261103-0002', DeliveryNoteNumber::nextManual());

        Carbon::setTestNow(Carbon::parse('2026-11-04 08:00:00', 'Asia/Jakarta'));
        $this->assertSame('SJM-261104-0001', DeliveryNoteNumber::nextManual());
    }

    public function test_skips_numbers_already_used(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-03 10:00:00', 'Asia/Jakarta'));
        $this->createManual('SJM-261103-0001');

        $this->assertSame('SJM-261103-0002', DeliveryNoteNumber::nextManual());
    }

    public function test_manual_store_uses_new_format_and_keeps_existing_numbers(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-03 10:00:00', 'Asia/Jakarta'));
        $old = $this->createManual('SJ-OUT-MNL-20261102101010-AB12');

        $warehouse = Warehouse::create(['code' => 'GD_SJM', 'name' => 'GD_SJM', 'type' => 'display']);
        $user = User::factory()->create();
        $item = Item::create([
            'sku' => 'SKU-SJM',
            'name' => 'SKU-SJM',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
        ]);
        ItemStock::create(['item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'stock' => 10]);

        $payload = fn (?string $sj) => array_filter([
            'warehouse_id' => $warehouse->id,
            'transacted_at' => now()->format('Y-m-d H:i'),
            'recipient_name' => 'Budi',
            'recipient_phone' => '0812',
            'recipient_address' => 'Jl. A',
            'surat_jalan_no' => $sj,
            'items' => [['item_id' => $item->id, 'qty' => 1]],
        ], fn ($v) => $v !== null);

        $this->actingAs($user)->withoutMiddleware()
            ->postJson(route('admin.outbound.manuals.store'), $payload(null))
            ->assertOk();
        $this->actingAs($user)->withoutMiddleware()
            ->postJson(route('admin.outbound.manuals.store'), $payload('SJ-KETIK-SENDIRI'))
            ->assertOk();

        $numbers = OutboundTransaction::where('id', '!=', $old->id)->orderBy('id')->pluck('surat_jalan_no')->all();
        $this->assertSame(['SJM-261103-0001', 'SJ-KETIK-SENDIRI'], $numbers);
        $this->assertSame('SJ-OUT-MNL-20261102101010-AB12', $old->fresh()->surat_jalan_no);

        // Edit transaksi tanpa mengubah nomor: nomor tetap sama.
        $tx = OutboundTransaction::where('surat_jalan_no', 'SJM-261103-0001')->firstOrFail();
        $this->actingAs($user)->withoutMiddleware()
            ->putJson(route('admin.outbound.manuals.update', $tx->id), $payload('SJM-261103-0001'))
            ->assertOk();
        $this->assertSame('SJM-261103-0001', $tx->fresh()->surat_jalan_no);
    }

    private function createManual(string $sj): OutboundTransaction
    {
        $warehouse = Warehouse::firstOrCreate(['code' => 'GD_OLD'], ['name' => 'GD_OLD', 'type' => 'display']);
        $user = User::factory()->create();

        return OutboundTransaction::create([
            'code' => 'OUT-MNL-'.$sj,
            'type' => 'manual',
            'surat_jalan_no' => $sj,
            'warehouse_id' => $warehouse->id,
            'transacted_at' => now(),
            'created_by' => $user->id,
            'status' => 'pending_qc',
        ]);
    }
}
