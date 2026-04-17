<?php

namespace Tests\Feature\Admin;

use App\Imports\ItemsImport;
use App\Models\Item;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ItemsImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_accepts_lane_only_as_item_address(): void
    {
        $this->createDefaultWarehouse();

        $import = new ItemsImport();
        $import->collection(collect([
            new Collection([
                'sku' => 'SKU-IMPORT-001',
                'name' => 'Imported Item',
                'lane' => 'KAB',
            ]),
        ]));

        $item = Item::with('lane')->where('sku', 'SKU-IMPORT-001')->firstOrFail();

        $this->assertNull($item->location_id);
        $this->assertNotNull($item->lane_id);
        $this->assertSame('KAB', $item->address);
        $this->assertSame('KAB', $item->lane?->code);
    }

    public function test_import_rejects_incomplete_detailed_location_parts(): void
    {
        $this->createDefaultWarehouse();

        $import = new ItemsImport();

        try {
            $import->collection(collect([
                new Collection([
                    'sku' => 'SKU-IMPORT-001A',
                    'name' => 'Imported Item',
                    'lane' => 'KAB',
                    'rack' => 'A',
                ]),
            ]));

            $this->fail('Import seharusnya gagal jika alamat detail diisi tidak lengkap.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Baris 2 (SKU SKU-IMPORT-001A): lengkapi lane, rack, kolom, dan baris jika ingin mengisi lokasi item.',
                $exception->errors()['file'][0] ?? null
            );
        }

        $this->assertDatabaseMissing('items', [
            'sku' => 'SKU-IMPORT-001A',
        ]);
    }

    public function test_import_creates_location_when_all_location_parts_are_present(): void
    {
        $this->createDefaultWarehouse();

        $import = new ItemsImport();
        $import->collection(collect([
            new Collection([
                'sku' => 'SKU-IMPORT-002',
                'name' => 'Imported Item Full Location',
                'lane' => 'KAB',
                'rack' => 'A',
                'column' => '3',
                'row' => '5',
            ]),
        ]));

        $item = Item::with('lane', 'location.lane')->where('sku', 'SKU-IMPORT-002')->firstOrFail();

        $this->assertNotNull($item->location);
        $this->assertSame($item->location?->lane_id, $item->lane_id);
        $this->assertSame('KAB-A-03-05', $item->address);
        $this->assertSame('KAB-A-03-05', $item->location?->code);
        $this->assertSame('KAB', $item->lane?->code);
        $this->assertSame('KAB', $item->location?->lane?->code);
    }

    private function createDefaultWarehouse(): Warehouse
    {
        return Warehouse::firstOrCreate([
            'code' => 'GUDANG_BESAR',
        ], [
            'name' => 'Gudang Besar',
        ]);
    }
}
