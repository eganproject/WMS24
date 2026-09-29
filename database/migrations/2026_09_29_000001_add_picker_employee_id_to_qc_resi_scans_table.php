<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('qc_resi_scans', function (Blueprint $table) {
            $table->foreignId('picker_employee_id')
                ->nullable()
                ->after('resi_id')
                ->constrained('employees')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('qc_resi_scans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('picker_employee_id');
        });
    }
};
