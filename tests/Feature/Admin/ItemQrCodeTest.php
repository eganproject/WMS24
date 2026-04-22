<?php

namespace Tests\Feature\Admin;

use App\Models\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemQrCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_item_qr_code_route_returns_png_using_item_sku(): void
    {
        $item = Item::create([
            'sku' => 'SKU-QR-001',
            'name' => 'Item QR',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
        ]);

        $response = $this->withoutMiddleware()->get(route('admin.masterdata.items.qr-code', $item));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/png');
        $this->assertStringStartsWith("\x89PNG\r\n\x1a\n", $response->getContent());
    }

    public function test_item_qr_code_download_route_returns_attachment_filename(): void
    {
        $item = Item::create([
            'sku' => 'SKU QR/Download 002',
            'name' => 'Item QR Download',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
        ]);

        $response = $this->withoutMiddleware()->get(route('admin.masterdata.items.qr-code', [
            'item' => $item,
            'download' => 1,
        ]));

        $response->assertOk();
        $response->assertHeader(
            'Content-Disposition',
            'attachment; filename="qr-item-sku-qr-download-002.png"'
        );
        $response->assertHeader('Content-Type', 'image/png');
    }
}
