<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'lane_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('lane_id')->nullable()->after('id')->constrained('lanes')->nullOnDelete();
            });
        }

        if (Schema::hasTable('lanes') && Schema::hasColumn('lanes', 'divisi_id')) {
            Schema::table('lanes', function (Blueprint $table) {
                $table->dropConstrainedForeignId('divisi_id');
            });
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'divisi_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropConstrainedForeignId('divisi_id');
            });
        }

        if (Schema::hasTable('menus')) {
            $menuId = DB::table('menus')->where('slug', 'divisi')->value('id');
            if ($menuId) {
                if (Schema::hasTable('permission_menu')) {
                    DB::table('permission_menu')->where('menu_id', $menuId)->delete();
                }
                DB::table('menus')->where('id', $menuId)->delete();
            }
        }

        if (Schema::hasTable('divisis')) {
            Schema::dropIfExists('divisis');
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('divisis')) {
            Schema::create('divisis', function (Blueprint $table) {
                $table->id();
                $table->string('name', 150);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('lanes') && !Schema::hasColumn('lanes', 'divisi_id')) {
            Schema::table('lanes', function (Blueprint $table) {
                $table->foreignId('divisi_id')->nullable()->after('name')->constrained('divisis')->nullOnDelete();
            });
        }

        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'divisi_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('divisi_id')->nullable()->after('id')->constrained('divisis')->nullOnDelete();
            });
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'lane_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropConstrainedForeignId('lane_id');
            });
        }
    }
};
