<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('warehouses')) {
            return;
        }

        $code = config('inventory.damaged_warehouse_code', 'GUDANG_RUSAK');
        $now = now();

        DB::table('warehouses')->updateOrInsert(
            ['code' => $code],
            [
                'name' => 'Gudang Rusak',
                'type' => 'damaged',
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );
    }

    public function down(): void
    {
        // Intentionally left blank. Deleting warehouse master data can invalidate stock history.
    }
};
