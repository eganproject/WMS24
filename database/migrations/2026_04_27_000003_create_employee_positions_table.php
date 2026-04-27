<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_positions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('position_id')->nullable()->after('area_id')->constrained('employee_positions')->nullOnDelete();
        });

        if (Schema::hasColumn('employees', 'position')) {
            $positions = DB::table('employees')
                ->whereNotNull('position')
                ->where('position', '!=', '')
                ->select('position')
                ->distinct()
                ->pluck('position');

            foreach ($positions as $positionName) {
                DB::table('employee_positions')->updateOrInsert(
                    ['name' => $positionName],
                    [
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );

                $positionId = DB::table('employee_positions')->where('name', $positionName)->value('id');
                DB::table('employees')->where('position', $positionName)->update(['position_id' => $positionId]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('employees') && Schema::hasColumn('employees', 'position_id')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropConstrainedForeignId('position_id');
            });
        }

        Schema::dropIfExists('employee_positions');
    }
};
