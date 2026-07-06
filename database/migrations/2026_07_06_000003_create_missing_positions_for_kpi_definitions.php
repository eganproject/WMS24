<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('kpi_definitions') || !Schema::hasTable('employee_positions')) {
            return;
        }

        $hasPositionColumn = Schema::hasColumn('kpi_definitions', 'employee_position_id');
        if (!$hasPositionColumn) {
            return;
        }

        $now = now();
        $existingPositionNames = DB::table('employee_positions')
            ->pluck('name')
            ->mapWithKeys(fn ($name) => [$this->normalize($name) => true]);

        DB::table('kpi_definitions')
            ->select('role_name')
            ->whereNotNull('role_name')
            ->distinct()
            ->orderBy('role_name')
            ->pluck('role_name')
            ->each(function ($roleName) use (&$existingPositionNames, $now) {
                $normalized = $this->normalize($roleName);
                if ($normalized === '' || $existingPositionNames->has($normalized)) {
                    return;
                }

                DB::table('employee_positions')->insert([
                    'name' => $roleName,
                    'description' => 'Dibuat otomatis dari role KPI agar KPI Master terhubung ke master jabatan.',
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $existingPositionNames->put($normalized, true);
            });

        $positions = DB::table('employee_positions')
            ->get(['id', 'name'])
            ->mapWithKeys(fn ($position) => [$this->normalize($position->name) => $position]);

        DB::table('kpi_definitions')
            ->whereNull('employee_position_id')
            ->orderBy('id')
            ->get(['id', 'role_name'])
            ->each(function ($definition) use ($positions, $now) {
                $position = $positions->get($this->normalize($definition->role_name));
                if (!$position) {
                    return;
                }

                DB::table('kpi_definitions')
                    ->where('id', $definition->id)
                    ->update([
                        'employee_position_id' => $position->id,
                        'updated_at' => $now,
                    ]);
            });
    }

    public function down(): void
    {
        // Intentionally keep generated positions and links to avoid removing user-visible master data.
    }

    private function normalize(?string $value): string
    {
        return Str::of($value ?? '')
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->toString();
    }
};
