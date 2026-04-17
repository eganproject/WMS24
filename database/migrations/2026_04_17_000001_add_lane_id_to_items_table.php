<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->foreignId('lane_id')
                ->nullable()
                ->after('category_id')
                ->constrained('lanes')
                ->nullOnDelete();
        });

        $itemsWithLocation = DB::table('items')
            ->join('locations', 'locations.id', '=', 'items.location_id')
            ->select('items.id', 'locations.lane_id')
            ->orderBy('items.id')
            ->get();

        foreach ($itemsWithLocation as $row) {
            DB::table('items')
                ->where('id', $row->id)
                ->update(['lane_id' => $row->lane_id]);
        }

        $laneIdsByCode = DB::table('lanes')
            ->select('id', 'code')
            ->get()
            ->mapWithKeys(fn ($lane) => [strtoupper(trim((string) $lane->code)) => (int) $lane->id]);

        DB::table('items')
            ->whereNull('lane_id')
            ->whereNotNull('address')
            ->orderBy('id')
            ->chunkById(200, function ($items) use ($laneIdsByCode) {
                foreach ($items as $item) {
                    $normalizedAddress = strtoupper(trim((string) ($item->address ?? '')));
                    if ($normalizedAddress === '') {
                        continue;
                    }

                    $laneId = $laneIdsByCode->get($normalizedAddress);
                    if (!$laneId) {
                        continue;
                    }

                    DB::table('items')
                        ->where('id', $item->id)
                        ->update(['lane_id' => $laneId]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lane_id');
        });
    }
};
