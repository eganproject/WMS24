<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('lanes') && !Schema::hasTable('areas')) {
            Schema::rename('lanes', 'areas');
        }

        if (Schema::hasTable('menus')) {
            DB::table('menus')
                ->where('slug', 'lanes')
                ->update([
                    'name' => 'Areas',
                    'slug' => 'areas',
                    'route' => 'admin.masterdata.areas.index',
                    'updated_at' => now(),
                ]);
        }

        if (Schema::hasTable('locations') && Schema::hasColumn('locations', 'lane_id') && !Schema::hasColumn('locations', 'area_id')) {
            Schema::table('locations', function (Blueprint $table) {
                $table->renameColumn('lane_id', 'area_id');
            });
        }

        if (Schema::hasTable('items') && Schema::hasColumn('items', 'lane_id') && !Schema::hasColumn('items', 'area_id')) {
            Schema::table('items', function (Blueprint $table) {
                $table->renameColumn('lane_id', 'area_id');
            });
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'lane_id') && !Schema::hasColumn('users', 'area_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->renameColumn('lane_id', 'area_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('menus')) {
            DB::table('menus')
                ->where('slug', 'areas')
                ->update([
                    'name' => 'Lanes',
                    'slug' => 'lanes',
                    'route' => 'admin.masterdata.lanes.index',
                    'updated_at' => now(),
                ]);
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'area_id') && !Schema::hasColumn('users', 'lane_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->renameColumn('area_id', 'lane_id');
            });
        }

        if (Schema::hasTable('items') && Schema::hasColumn('items', 'area_id') && !Schema::hasColumn('items', 'lane_id')) {
            Schema::table('items', function (Blueprint $table) {
                $table->renameColumn('area_id', 'lane_id');
            });
        }

        if (Schema::hasTable('locations') && Schema::hasColumn('locations', 'area_id') && !Schema::hasColumn('locations', 'lane_id')) {
            Schema::table('locations', function (Blueprint $table) {
                $table->renameColumn('area_id', 'lane_id');
            });
        }

        if (Schema::hasTable('areas') && !Schema::hasTable('lanes')) {
            Schema::rename('areas', 'lanes');
        }
    }
};
