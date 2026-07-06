<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('kpi_definitions') || !Schema::hasTable('employee_positions')) {
            return;
        }

        Schema::table('kpi_definitions', function (Blueprint $table) {
            if (!Schema::hasColumn('kpi_definitions', 'employee_position_id')) {
                $table->foreignId('employee_position_id')
                    ->nullable()
                    ->after('role_name')
                    ->constrained('employee_positions')
                    ->nullOnDelete();
                $table->index(['employee_position_id', 'is_active'], 'kpi_def_position_active_idx');
            }
        });

        $positions = DB::table('employee_positions')->get(['id', 'name']);
        if ($positions->isEmpty()) {
            return;
        }

        $positionByNormalizedName = $positions->mapWithKeys(fn ($position) => [
            $this->normalize($position->name) => $position,
        ]);

        $rolePositionKeywords = [
            'Supervisor' => ['supervisor', 'spv'],
            'Stock Controller' => ['stock controller', 'stock control', 'inventory control', 'controller stok', 'admin stok', 'staff stok'],
            'PIC Transfer Gudang' => ['pic transfer', 'transfer gudang', 'admin transfer', 'staff transfer'],
            'Admin Sistem' => ['admin sistem', 'system admin', 'administrator sistem', 'it admin'],
            'Admin Inbound' => ['admin inbound', 'inbound admin'],
            'Staff Gudang Inbound' => ['staff gudang inbound', 'staff inbound', 'gudang inbound'],
            'Admin Outbound' => ['admin outbound', 'outbound admin'],
            'QC' => ['qc', 'quality control'],
            'Picker' => ['picker', 'picking'],
            'Packer' => ['packer', 'packing'],
            'Scan Outbound' => ['scan outbound', 'scan out', 'scanner outbound', 'operator scan out'],
            'Admin Return' => ['admin return', 'admin retur', 'return admin', 'retur admin'],
            'Staff Gudang Return' => ['staff gudang return', 'staff gudang retur', 'staff return', 'staff retur', 'gudang return', 'gudang retur'],
            'HR / Admin Absensi' => ['hr', 'human resource', 'admin absensi', 'absensi'],
        ];

        DB::table('kpi_definitions')
            ->whereNull('employee_position_id')
            ->orderBy('id')
            ->get(['id', 'role_name'])
            ->each(function ($definition) use ($positions, $positionByNormalizedName, $rolePositionKeywords) {
                $position = $positionByNormalizedName->get($this->normalize($definition->role_name));

                if (!$position && isset($rolePositionKeywords[$definition->role_name])) {
                    $keywords = $rolePositionKeywords[$definition->role_name];
                    $position = $positions->first(function ($candidate) use ($keywords) {
                        $name = $this->normalize($candidate->name);

                        foreach ($keywords as $keyword) {
                            if (Str::contains($name, $keyword)) {
                                return true;
                            }
                        }

                        return false;
                    });
                }

                if (!$position) {
                    return;
                }

                DB::table('kpi_definitions')
                    ->where('id', $definition->id)
                    ->update([
                        'employee_position_id' => $position->id,
                        'role_name' => $position->name,
                        'updated_at' => now(),
                    ]);
            });
    }

    public function down(): void
    {
        if (!Schema::hasTable('kpi_definitions') || !Schema::hasColumn('kpi_definitions', 'employee_position_id')) {
            return;
        }

        Schema::table('kpi_definitions', function (Blueprint $table) {
            $table->dropIndex('kpi_def_position_active_idx');
            $table->dropConstrainedForeignId('employee_position_id');
        });
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
