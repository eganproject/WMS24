<?php

namespace Tests\Feature\Outbound;

use App\Models\Item;
use App\Models\PickerSession;
use App\Models\PickerSessionItem;
use App\Models\PickingList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PickerSessionFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_session_includes_remaining_picking_qty_for_each_item(): void
    {
        $user = User::factory()->create();
        $today = now()->toDateString();

        $listedItem = Item::create([
            'sku' => 'SKU-PICK-001',
            'name' => 'Listed Item',
            'category_id' => 0,
        ]);

        $extraItem = Item::create([
            'sku' => 'SKU-PICK-002',
            'name' => 'Extra Item',
            'category_id' => 0,
        ]);

        $session = PickerSession::create([
            'code' => 'PKR-TEST-001',
            'user_id' => $user->id,
            'status' => 'draft',
            'started_at' => now(),
        ]);

        PickerSessionItem::create([
            'picker_session_id' => $session->id,
            'item_id' => $listedItem->id,
            'qty' => 3,
        ]);

        PickerSessionItem::create([
            'picker_session_id' => $session->id,
            'item_id' => $extraItem->id,
            'qty' => 1,
        ]);

        PickingList::create([
            'list_date' => $today,
            'sku' => $listedItem->sku,
            'qty' => 10,
            'remaining_qty' => 7,
        ]);

        $response = $this->actingAs($user)->getJson(route('picker.current'));

        $response->assertOk()
            ->assertJsonPath('session.code', 'PKR-TEST-001');

        $items = collect($response->json('session.items'))->keyBy('sku');

        $this->assertSame(7, $items[$listedItem->sku]['picking_remaining_qty']);
        $this->assertSame(0, $items[$extraItem->sku]['picking_remaining_qty']);
    }
}
