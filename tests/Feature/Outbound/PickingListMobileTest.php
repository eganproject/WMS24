<?php

namespace Tests\Feature\Outbound;

use App\Models\Item;
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

        $this->actingAs($user)
            ->getJson(route('picker.picking-list.data', ['date' => now()->toDateString()]))
            ->assertOk()
            ->assertJsonPath('items.0.item_id', $item->id)
            ->assertJsonPath('items.0.sku', $item->sku)
            ->assertJsonPath('items.0.remaining_qty', 5);
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
