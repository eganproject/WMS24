<?php

namespace Tests\Feature\Outbound;

use App\Models\Item;
use App\Models\QcScanException;
use App\Models\PickingList;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PickingListMobileFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_picking_list_mobile_returns_remaining_qty_and_excludes_exception_sku(): void
    {
        $pickerUser = $this->createUserWithRole('picker');
        $today = now()->toDateString();

        Item::create([
            'sku' => 'SKU-PICK-001',
            'name' => 'Listed Item',
            'category_id' => 0,
        ]);

        Item::create([
            'sku' => 'SKU-PICK-002',
            'name' => 'Excluded Item',
            'category_id' => 0,
        ]);

        PickingList::create([
            'list_date' => $today,
            'sku' => 'SKU-PICK-001',
            'qty' => 10,
            'remaining_qty' => 7,
        ]);

        PickingList::create([
            'list_date' => $today,
            'sku' => 'SKU-PICK-002',
            'qty' => 5,
            'remaining_qty' => 5,
        ]);

        QcScanException::create([
            'sku' => 'SKU-PICK-002',
            'note' => 'SKU dikecualikan dari picking list mobile',
        ]);

        $response = $this->actingAs($pickerUser)->getJson(route('mobile.picking-list.data', [
            'date' => $today,
            'per_page' => 10,
        ]));

        $response->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('items.0.sku', 'SKU-PICK-001')
            ->assertJsonPath('items.0.remaining_qty', 7);
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
