<?php

namespace Tests\Feature\Outbound;

use App\Models\Divisi;
use App\Models\Item;
use App\Models\Lane;
use App\Models\PickingList;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PickingListMobileTest extends TestCase
{
    use RefreshDatabase;

    public function test_picker_mobile_list_data_includes_item_id_for_direct_pick(): void
    {
        $user = $this->createUserWithRole('picker');
        $item = Item::create([
            'sku' => 'SKU-PICK-LIST-001',
            'name' => 'List Item',
            'category_id' => 0,
        ]);

        PickingList::create([
            'list_date' => now()->toDateString(),
            'sku' => $item->sku,
            'qty' => 8,
            'remaining_qty' => 5,
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('picker.picking-list.data', ['date' => now()->toDateString()]));

        $response
            ->assertOk()
            ->assertJsonPath('items.0.item_id', $item->id)
            ->assertJsonPath('items.0.sku', $item->sku)
            ->assertJsonPath('items.0.remaining_qty', 5);
    }

    public function test_picker_mobile_list_uses_item_lane_when_detail_location_is_not_set(): void
    {
        Divisi::create(['name' => 'tanpa divisi']);
        $divisiA = Divisi::create(['name' => 'Divisi A']);
        $divisiB = Divisi::create(['name' => 'Divisi B']);

        $laneA = Lane::create([
            'code' => 'KAB',
            'name' => 'Kabinet',
            'divisi_id' => $divisiA->id,
            'is_active' => true,
        ]);
        $laneB = Lane::create([
            'code' => 'DSP',
            'name' => 'Display',
            'divisi_id' => $divisiB->id,
            'is_active' => true,
        ]);

        $visibleItem = Item::create([
            'sku' => 'SKU-PICK-LIST-002',
            'name' => 'Lane Only Item',
            'category_id' => 0,
            'lane_id' => $laneA->id,
            'address' => $laneA->code,
        ]);
        $hiddenItem = Item::create([
            'sku' => 'SKU-PICK-LIST-003',
            'name' => 'Other Division Item',
            'category_id' => 0,
            'lane_id' => $laneB->id,
            'address' => $laneB->code,
        ]);

        PickingList::create([
            'list_date' => now()->toDateString(),
            'sku' => $visibleItem->sku,
            'qty' => 6,
            'remaining_qty' => 4,
        ]);
        PickingList::create([
            'list_date' => now()->toDateString(),
            'sku' => $hiddenItem->sku,
            'qty' => 3,
            'remaining_qty' => 3,
        ]);

        $user = $this->createUserWithRole('picker');
        $user->divisi_id = $divisiA->id;
        $user->save();

        $this->actingAs($user)
            ->getJson(route('picker.picking-list.data', [
                'date' => now()->toDateString(),
                'lane_id' => $laneA->id,
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.item_id', $visibleItem->id)
            ->assertJsonPath('items.0.sku', $visibleItem->sku)
            ->assertJsonPath('items.0.address', $laneA->code);
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
