<?php

namespace Tests\Feature\Admin;

use App\Models\Item;
use App\Support\InboundScanExpectation;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InboundScanExpectationTest extends TestCase
{
    public function test_resolve_derives_koli_from_qty_when_qty_matches_item_koli_qty(): void
    {
        $item = new Item([
            'sku' => 'SKU-INB-001',
            'koli_qty' => 12,
        ]);

        $resolved = InboundScanExpectation::resolve($item, 24);

        $this->assertSame(24, $resolved['qty']);
        $this->assertSame(2, $resolved['koli']);
        $this->assertSame(12, $resolved['qty_per_koli']);
    }

    public function test_resolve_rejects_qty_that_is_not_multiple_of_item_koli_qty(): void
    {
        $item = new Item([
            'sku' => 'SKU-INB-002',
            'koli_qty' => 12,
        ]);

        try {
            InboundScanExpectation::resolve($item, 25);
            $this->fail('ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertSame(
                'Qty SKU SKU-INB-002 tidak bisa dibagi rata per koli. Isi/koli 12, qty inbound 25.',
                $e->errors()['items'][0] ?? null
            );
        }
    }
}
