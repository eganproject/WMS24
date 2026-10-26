<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('good_reworks', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->foreignId('source_warehouse_id')->constrained('warehouses');
            $table->foreignId('target_warehouse_id')->constrained('warehouses');
            $table->foreignId('recipe_id')->nullable()->constrained('rework_recipes')->nullOnDelete();
            $table->unsignedInteger('recipe_multiplier')->nullable();
            $table->string('source_ref', 100)->nullable();
            $table->timestamp('transacted_at')->useCurrent();
            $table->text('note')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'transacted_at']);
        });

        Schema::create('good_rework_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('good_rework_id')->constrained('good_reworks')->cascadeOnDelete();
            $table->string('line_type', 20);
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->integer('qty');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['good_rework_id', 'line_type']);
        });

        $this->addMenu();
    }

    public function down(): void
    {
        if (Schema::hasTable('menus')) {
            $menuId = DB::table('menus')->where('slug', 'good-reworks')->value('id');
            if ($menuId && Schema::hasTable('permission_menu')) {
                DB::table('permission_menu')->where('menu_id', $menuId)->delete();
            }
            DB::table('menus')->where('slug', 'good-reworks')->delete();
        }

        Schema::dropIfExists('good_rework_items');
        Schema::dropIfExists('good_reworks');
    }

    private function addMenu(): void
    {
        if (!Schema::hasTable('menus')) {
            return;
        }

        $inventoryMenuId = DB::table('menus')->where('slug', 'inventory')->value('id');
        if (!$inventoryMenuId) {
            return;
        }

        // Hanya menambah menu baru jika belum ada; menu yang sudah ada tidak diubah.
        if (!DB::table('menus')->where('slug', 'good-reworks')->exists()) {
            DB::table('menus')->insert([
                'name' => 'Rework Barang Bagus',
                'slug' => 'good-reworks',
                'route' => 'admin.inventory.good-reworks.index',
                'icon' => 'fas fa-tools',
                'parent_id' => $inventoryMenuId,
                'sort_order' => 13.35,
                'is_active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]);
        }

        if (!Schema::hasTable('roles') || !Schema::hasTable('permission_menu')) {
            return;
        }

        $menuId = DB::table('menus')->where('slug', 'good-reworks')->value('id');
        $roleIds = DB::table('roles')->whereIn('slug', ['admin'])->pluck('id');

        foreach ($roleIds as $roleId) {
            $exists = DB::table('permission_menu')
                ->where('role_id', $roleId)
                ->where('menu_id', $menuId)
                ->exists();
            if ($exists) {
                continue;
            }

            DB::table('permission_menu')->insert([
                'role_id' => $roleId,
                'menu_id' => $menuId,
                'can_view' => true,
                'can_create' => true,
                'can_update' => true,
                'can_delete' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]);
        }
    }
};
