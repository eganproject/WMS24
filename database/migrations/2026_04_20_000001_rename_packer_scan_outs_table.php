<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('packer_scan_outs') && !Schema::hasTable('shipment_scan_outs')) {
            Schema::rename('packer_scan_outs', 'shipment_scan_outs');
        }

        if (Schema::hasTable('shipment_scan_outs')) {
            Schema::table('shipment_scan_outs', function (Blueprint $table) {
                if (!Schema::hasColumn('shipment_scan_outs', 'kurir_id')) {
                    $table->foreignId('kurir_id')->nullable()->after('resi_id')->constrained('kurirs')->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('shipment_scan_outs') && !Schema::hasTable('packer_scan_outs')) {
            Schema::rename('shipment_scan_outs', 'packer_scan_outs');
        }
    }
};
