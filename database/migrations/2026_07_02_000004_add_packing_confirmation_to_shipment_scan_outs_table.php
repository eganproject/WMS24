<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('shipment_scan_outs')) {
            return;
        }

        Schema::table('shipment_scan_outs', function (Blueprint $table) {
            if (!Schema::hasColumn('shipment_scan_outs', 'packed_employee_id')) {
                $table->foreignId('packed_employee_id')
                    ->nullable()
                    ->after('scanned_by')
                    ->constrained('employees')
                    ->nullOnDelete();
            }
            if (!Schema::hasColumn('shipment_scan_outs', 'packed_at')) {
                $table->timestamp('packed_at')
                    ->nullable()
                    ->after('packed_employee_id');
            }
            if (!Schema::hasColumn('shipment_scan_outs', 'packing_confirmed_by')) {
                $table->foreignId('packing_confirmed_by')
                    ->nullable()
                    ->after('packed_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('shipment_scan_outs')) {
            return;
        }

        Schema::table('shipment_scan_outs', function (Blueprint $table) {
            if (Schema::hasColumn('shipment_scan_outs', 'packing_confirmed_by')) {
                $table->dropConstrainedForeignId('packing_confirmed_by');
            }
            if (Schema::hasColumn('shipment_scan_outs', 'packed_employee_id')) {
                $table->dropConstrainedForeignId('packed_employee_id');
            }
            if (Schema::hasColumn('shipment_scan_outs', 'packed_at')) {
                $table->dropColumn('packed_at');
            }
        });
    }
};
