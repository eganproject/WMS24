<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->foreignId('area_id')
                ->nullable()
                ->after('category_id')
                ->constrained('areas')
                ->nullOnDelete();
        });

        $itemsWithLocation = DB::table('items')
            ->join('locations', 'locations.id', '=', 'items.location_id')
            ->select('items.id', 'locations.area_id')
            ->orderBy('items.id')
            ->get();

        foreach ($itemsWithLocation as $row) {
            DB::table('items')
                ->where('id', $row->id)
                ->update(['area_id' => $row->area_id]);
        }

        $areaIdsByCode = DB::table('areas')
            ->select('id', 'code')
            ->get()
            ->mapWithKeys(fn ($area) => [strtoupper(trim((string) $area->code)) => (int) $area->id]);

        DB::table('items')
            ->whereNull('area_id')
            ->whereNotNull('address')
            ->orderBy('id')
            ->chunkById(200, function ($items) use ($areaIdsByCode) {
                foreach ($items as $item) {
                    $normalizedAddress = strtoupper(trim((string) ($item->address ?? '')));
                    if ($normalizedAddress === '') {
                        continue;
                    }

                    $areaId = $areaIdsByCode->get($normalizedAddress);
                    if (!$areaId) {
                        continue;
                    }

                    DB::table('items')
                        ->where('id', $item->id)
                        ->update(['area_id' => $areaId]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('area_id');
        });
    }
};
