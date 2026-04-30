<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Fix any existing rows with invalid qty
        DB::table('resi_details')->where('qty', '<=', 0)->update(['qty' => 1]);

        try {
            DB::statement('ALTER TABLE resi_details ADD CONSTRAINT resi_details_qty_positive CHECK (qty > 0)');
        } catch (\Throwable) {
            // Constraint may already exist, or DB driver doesn't enforce CHECK
        }
    }

    public function down(): void
    {
        try {
            DB::statement('ALTER TABLE resi_details DROP CONSTRAINT resi_details_qty_positive');
        } catch (\Throwable) {
            // ignore
        }
    }
};
