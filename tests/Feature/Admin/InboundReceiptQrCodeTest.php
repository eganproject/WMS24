<?php

namespace Tests\Feature\Admin;

use App\Models\InboundItem;
use App\Models\InboundTransaction;
use App\Models\Item;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InboundReceiptQrCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_receipt_qr_preview_returns_receipt_items_and_pdf_link(): void
    {
        $transaction = $this->createReceiptTransaction();

        $response = $this->withoutMiddleware()->getJson(
            route('admin.inbound.receipts.qr-preview', $transaction->id)
        );

        $response->assertOk();
        $response->assertJsonPath('code', $transaction->code);
        $response->assertJsonPath('items_count', 1);
        $response->assertJsonPath('transacted_at', $transaction->transacted_at?->format('Y-m-d H:i'));
        $response->assertJsonPath('transacted_period', $transaction->transacted_at?->format('m.y'));
        $response->assertJsonPath('items.0.sku', 'SKU-RCV-QR-001');
        $response->assertJsonPath('items.0.qty', 24);
        $response->assertJsonPath('items.0.koli', 2);
        $this->assertStringStartsWith('data:image/png;base64,', (string) $response->json('code_barcode_data_url'));
        $response->assertJsonPath(
            'items.0.qr_url',
            route('admin.masterdata.items.qr-code', ['item' => $transaction->items()->first()->item_id])
        );
        $response->assertJsonPath(
            'pdf_url',
            route('admin.inbound.receipts.qr-pdf', $transaction->id)
        );
    }

    public function test_receipt_qr_pdf_download_returns_pdf_file(): void
    {
        $transaction = $this->createReceiptTransaction();

        $response = $this->withoutMiddleware()->get(
            route('admin.inbound.receipts.qr-pdf', $transaction->id)
        );

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $response->assertHeader(
            'content-disposition',
            'attachment; filename="qr-penerimaan-'.$this->expectedSlug($transaction->code).'.pdf"'
        );

        $content = $response->getContent();
        $this->assertStringStartsWith('%PDF-', $content);
        $this->assertStringContainsString('/Type /Catalog', $content);
    }

    private function createReceiptTransaction(): InboundTransaction
    {
        $warehouse = Warehouse::firstOrCreate([
            'code' => 'GUDANG_BESAR',
        ], [
            'name' => 'Gudang Besar',
            'type' => 'main',
        ]);

        $item = Item::create([
            'sku' => 'SKU-RCV-QR-001',
            'name' => 'Item Receipt QR',
            'item_type' => Item::TYPE_SINGLE,
            'category_id' => 0,
            'koli_qty' => 12,
        ]);

        $transaction = InboundTransaction::create([
            'code' => 'INB-RCV-QR-001',
            'type' => 'receipt',
            'warehouse_id' => $warehouse->id,
            'transacted_at' => now(),
            'status' => 'pending_scan',
        ]);

        InboundItem::create([
            'inbound_transaction_id' => $transaction->id,
            'item_id' => $item->id,
            'qty' => 24,
            'koli' => 2,
            'note' => 'Test receipt QR',
        ]);

        return $transaction->fresh(['items.item']);
    }

    private function expectedSlug(string $value): string
    {
        $slug = preg_replace('/[^a-z0-9]+/i', '-', trim($value)) ?? '';
        $slug = trim(strtolower($slug), '-');

        return $slug !== '' ? $slug : 'penerimaan';
    }
}
