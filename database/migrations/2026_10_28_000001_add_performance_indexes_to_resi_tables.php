<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the columns that resi import, QC scan, scan out and picking list
 * search on. Without them MySQL scans the whole resis table (and, inside
 * lockForUpdate(), locks every row it scans).
 */
return new class extends Migration
{
    /** @var array<string,array<string,array<int,string>>> table => index name => columns */
    private array $indexes = [
        'resis' => [
            'resis_id_pesanan_index' => ['id_pesanan'],
            'resis_no_resi_index' => ['no_resi'],
            'resis_tanggal_upload_status_index' => ['tanggal_upload', 'status'],
        ],
        'resi_details' => [
            'resi_details_sku_index' => ['sku'],
        ],
        'qc_resi_scan_items' => [
            'qc_resi_scan_items_sku_index' => ['sku'],
        ],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $table => $indexes) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            foreach ($indexes as $name => $columns) {
                if (Schema::hasIndex($table, $name) || Schema::hasIndex($table, $columns)) {
                    continue;
                }
                Schema::table($table, fn (Blueprint $t) => $t->index($columns, $name));
            }
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $table => $indexes) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            foreach (array_keys($indexes) as $name) {
                if (Schema::hasIndex($table, $name)) {
                    Schema::table($table, fn (Blueprint $t) => $t->dropIndex($name));
                }
            }
        }
    }
};
