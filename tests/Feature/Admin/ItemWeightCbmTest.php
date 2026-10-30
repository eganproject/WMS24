<?php

namespace Tests\Feature\Admin;

use App\Models\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemWeightCbmTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): self
    {
        return $this->actingAs(\App\Models\User::factory()->create())
            ->withoutMiddleware([
                \App\Http\Middleware\AuthorizeMenuPermission::class,
                \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            ]);
    }

    public function test_item_without_weight_and_cbm_is_still_valid(): void
    {
        $this->admin()->postJson(route('admin.masterdata.items.store'), [
            'sku' => 'SKU-NOW',
            'name' => 'Tanpa Berat',
            'item_type' => Item::TYPE_SINGLE,
        ])->assertOk();

        $item = Item::where('sku', 'SKU-NOW')->firstOrFail();
        $this->assertNull($item->weight_per_pcs);
        $this->assertNull($item->weight_per_koli);
        $this->assertNull($item->cbm_per_koli);
    }

    public function test_item_stores_weight_and_cbm_and_shows_them_on_detail(): void
    {
        $this->admin()->postJson(route('admin.masterdata.items.store'), [
            'sku' => 'SKU-W',
            'name' => 'Item Berat',
            'item_type' => Item::TYPE_SINGLE,
            'koli_qty' => 24,
            'weight_per_pcs' => '0.5',
            'weight_per_koli' => '12.25',
            'cbm_per_koli' => '0.045',
        ])->assertOk();

        $item = Item::where('sku', 'SKU-W')->firstOrFail();
        $this->assertSame('0.500', $item->weight_per_pcs);
        $this->assertSame('12.250', $item->weight_per_koli);
        $this->assertSame('0.045000', $item->cbm_per_koli);

        $this->admin()->get(route('admin.masterdata.items.show', $item))
            ->assertOk()
            ->assertSee('12,25 kg')
            ->assertSee('0,045 m³')
            ->assertSee('0,5 kg');

        $this->admin()->get(route('admin.masterdata.items.edit', $item))
            ->assertOk()
            ->assertSee('item_weight_per_koli');
    }

    public function test_update_can_clear_values_and_rejects_negative(): void
    {
        $item = Item::create([
            'sku' => 'SKU-U',
            'name' => 'Item',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
            'weight_per_pcs' => 1,
            'weight_per_koli' => 10,
            'cbm_per_koli' => 0.1,
        ]);

        $this->admin()->putJson(route('admin.masterdata.items.update', $item), [
            'sku' => 'SKU-U',
            'name' => 'Item',
            'item_type' => Item::TYPE_SINGLE,
            'weight_per_koli' => -1,
        ])->assertStatus(422)->assertJsonValidationErrors('weight_per_koli');

        $this->admin()->putJson(route('admin.masterdata.items.update', $item), [
            'sku' => 'SKU-U',
            'name' => 'Item',
            'item_type' => Item::TYPE_SINGLE,
            'weight_per_pcs' => '',
            'weight_per_koli' => '',
            'cbm_per_koli' => '',
        ])->assertOk();

        $item->refresh();
        $this->assertNull($item->weight_per_pcs);
        $this->assertNull($item->weight_per_koli);
        $this->assertNull($item->cbm_per_koli);
    }

    public function test_update_without_fields_keeps_existing_values(): void
    {
        $item = Item::create([
            'sku' => 'SKU-K',
            'name' => 'Item',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
            'weight_per_koli' => 10,
        ]);

        $this->admin()->putJson(route('admin.masterdata.items.update', $item), [
            'sku' => 'SKU-K',
            'name' => 'Item Baru',
            'item_type' => Item::TYPE_SINGLE,
        ])->assertOk();

        $this->assertSame('10.000', $item->fresh()->weight_per_koli);
    }
}
