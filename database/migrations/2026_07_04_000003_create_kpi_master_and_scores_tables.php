<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('kpi_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('role_name', 100);
            $table->string('metric_name', 150);
            $table->text('description')->nullable();
            $table->string('target_operator', 5)->default('>=');
            $table->decimal('target_value', 15, 4)->default(0);
            $table->string('unit', 50)->nullable();
            $table->decimal('weight', 8, 2)->default(100);
            $table->string('period_type', 20)->default('monthly');
            $table->string('source_type', 20)->default('manual');
            $table->string('formula_key', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['role_name', 'metric_name'], 'kpi_def_role_metric_unique');
            $table->index(['role_name', 'is_active'], 'kpi_def_role_active_idx');
            $table->index(['source_type', 'formula_key'], 'kpi_def_source_formula_idx');
        });

        Schema::create('kpi_employee_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('kpi_definition_id')->constrained('kpi_definitions')->cascadeOnDelete();
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->decimal('target_value', 15, 4)->nullable();
            $table->decimal('weight', 8, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'effective_from', 'effective_until'], 'kpi_assign_employee_period_idx');
            $table->index(['kpi_definition_id', 'is_active'], 'kpi_assign_definition_active_idx');
        });

        Schema::create('kpi_score_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status', 20)->default('draft');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();

            $table->index(['period_start', 'period_end', 'status'], 'kpi_snap_period_status_idx');
        });

        Schema::create('kpi_score_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kpi_score_snapshot_id')->constrained('kpi_score_snapshots')->cascadeOnDelete();
            $table->foreignId('kpi_definition_id')->nullable()->constrained('kpi_definitions')->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('role_name', 100);
            $table->string('metric_name', 150);
            $table->string('target_operator', 5)->default('>=');
            $table->decimal('target_value', 15, 4)->default(0);
            $table->decimal('actual_value', 15, 4)->nullable();
            $table->decimal('achievement_percent', 8, 2)->default(0);
            $table->decimal('score', 8, 2)->default(0);
            $table->decimal('weight', 8, 2)->default(100);
            $table->decimal('weighted_score', 8, 2)->default(0);
            $table->string('source_type', 20)->default('manual');
            $table->string('formula_key', 100)->nullable();
            $table->text('note')->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();

            $table->index(['kpi_score_snapshot_id', 'employee_id'], 'kpi_item_snapshot_employee_idx');
            $table->index(['kpi_score_snapshot_id', 'kpi_definition_id'], 'kpi_item_snapshot_definition_idx');
            $table->index('role_name', 'kpi_item_role_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_score_items');
        Schema::dropIfExists('kpi_score_snapshots');
        Schema::dropIfExists('kpi_employee_assignments');
        Schema::dropIfExists('kpi_definitions');
    }
};
