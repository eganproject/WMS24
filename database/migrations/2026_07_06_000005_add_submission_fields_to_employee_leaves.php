<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('employee_leaves')) {
            return;
        }

        Schema::table('employee_leaves', function (Blueprint $table) {
            if (!Schema::hasColumn('employee_leaves', 'submitted_by_user_id')) {
                $table->foreignId('submitted_by_user_id')
                    ->nullable()
                    ->after('proof_image_path')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('employee_leaves', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable()->after('submitted_by_user_id');
            }

            if (!Schema::hasColumn('employee_leaves', 'submission_source')) {
                $table->string('submission_source', 40)->nullable()->after('submitted_at');
                $table->index('submission_source', 'employee_leaves_submission_source_idx');
            }
        });

        DB::table('employee_leaves')
            ->whereNull('submission_source')
            ->update([
                'submission_source' => 'admin_entry',
                'submitted_at' => DB::raw('created_at'),
            ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('employee_leaves')) {
            return;
        }

        Schema::table('employee_leaves', function (Blueprint $table) {
            if (Schema::hasColumn('employee_leaves', 'submission_source')) {
                $table->dropIndex('employee_leaves_submission_source_idx');
                $table->dropColumn('submission_source');
            }

            if (Schema::hasColumn('employee_leaves', 'submitted_at')) {
                $table->dropColumn('submitted_at');
            }

            if (Schema::hasColumn('employee_leaves', 'submitted_by_user_id')) {
                $table->dropConstrainedForeignId('submitted_by_user_id');
            }
        });
    }
};
