<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('packer_scan_exceptions') && !Schema::hasTable('qc_scan_exceptions')) {
            Schema::rename('packer_scan_exceptions', 'qc_scan_exceptions');
        }

        foreach ([
            'picker_session_items',
            'picker_sessions',
            'picker_transit_items',
            'packer_resi_scans',
            'packer_transit_histories',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        if (!Schema::hasTable('menus')) {
            return;
        }

        $obsoleteSlugs = [
            'picker-transit',
            'outbound-picker-history',
            'outbound-packer-history',
            'outbound-picker-report',
            'outbound-packer-packing-report',
        ];

        $obsoleteMenuIds = DB::table('menus')
            ->whereIn('slug', $obsoleteSlugs)
            ->pluck('id');

        if ($obsoleteMenuIds->isNotEmpty() && Schema::hasTable('permission_menu')) {
            DB::table('permission_menu')->whereIn('menu_id', $obsoleteMenuIds)->delete();
        }

        DB::table('menus')->whereIn('slug', $obsoleteSlugs)->delete();

        DB::table('menus')
            ->where('slug', 'outbound-packer-scan-outs')
            ->update([
                'name' => 'Riwayat Scan Out',
                'slug' => 'outbound-scan-out-history',
                'route' => 'admin.outbound.scan-out-history.index',
                'updated_at' => now(),
            ]);

        DB::table('menus')
            ->where('slug', 'outbound-packer-report')
            ->update([
                'name' => 'Laporan Scan Out',
                'slug' => 'outbound-scan-out-report',
                'route' => 'admin.reports.scan-out-reports.index',
                'updated_at' => now(),
            ]);

        DB::table('menus')
            ->where('slug', 'outbound-packer-scan-exceptions')
            ->update([
                'name' => 'SKU Exception QC',
                'slug' => 'outbound-qc-scan-exceptions',
                'route' => 'admin.outbound.qc-scan-exceptions.index',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('qc_scan_exceptions') && !Schema::hasTable('packer_scan_exceptions')) {
            Schema::rename('qc_scan_exceptions', 'packer_scan_exceptions');
        }

        if (!Schema::hasTable('menus')) {
            return;
        }

        DB::table('menus')
            ->where('slug', 'outbound-scan-out-history')
            ->update([
                'name' => 'Riwayat Scan Out',
                'slug' => 'outbound-packer-scan-outs',
                'route' => 'admin.outbound.packer-scan-outs.index',
                'updated_at' => now(),
            ]);

        DB::table('menus')
            ->where('slug', 'outbound-scan-out-report')
            ->update([
                'name' => 'Laporan Scan Out',
                'slug' => 'outbound-packer-report',
                'route' => 'admin.reports.packer-reports.index',
                'updated_at' => now(),
            ]);

        DB::table('menus')
            ->where('slug', 'outbound-qc-scan-exceptions')
            ->update([
                'name' => 'SKU Exception Packer',
                'slug' => 'outbound-packer-scan-exceptions',
                'route' => 'admin.outbound.packer-scan-exceptions.index',
                'updated_at' => now(),
            ]);
    }
};
