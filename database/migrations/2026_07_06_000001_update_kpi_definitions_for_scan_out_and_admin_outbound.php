<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('kpi_definitions')) {
            return;
        }

        $now = now();

        $packingInputId = $this->upsertDefinition([
            'role_name' => 'Scan Outbound',
            'metric_name' => 'Packing Employee Input Completion',
            'description' => $this->description(
                'Kelengkapan input karyawan yang melakukan packing pada proses scan out.',
                'Otomatis',
                'Ada',
                'Outbound > Scan Out, Scan Out History',
                'scan out dengan packed_employee_id / total scan out',
                'Operator scan out wajib memilih/input karyawan packer sebelum scan. Cek Scan Out History untuk paket tanpa packed by.',
                'KPI ini menggantikan Courier Sorting Accuracy dan mengukur disiplin input packer.'
            ),
            'target_operator' => '>=',
            'target_value' => 100,
            'unit' => '%',
            'weight' => 100,
            'period_type' => 'daily',
            'source_type' => 'auto',
            'formula_key' => 'packing_employee_input_completion',
            'is_active' => true,
            'created_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $duplicateAccuracyId = $this->upsertDefinition([
            'role_name' => 'Admin Outbound',
            'metric_name' => 'Resi Import Duplicate Accuracy',
            'description' => $this->description(
                'Akurasi admin tidak melakukan import resi yang sudah pernah diimport.',
                'Otomatis',
                'Ada',
                'Resi Import',
                '(total resi upload - duplikasi no_resi) / total resi upload',
                'Import resi melalui modul Resi Import. Sistem memeriksa no_resi duplikat pada periode snapshot sebagai indikator akurasi import.',
                'Saat ini dihitung dari data duplikat no_resi di tabel resis. Jika perlu audit attempt yang diblokir, tambahkan log failed import attempt.'
            ),
            'target_operator' => '>=',
            'target_value' => 100,
            'unit' => '%',
            'weight' => 100,
            'period_type' => 'daily',
            'source_type' => 'auto',
            'formula_key' => 'resi_import_duplicate_accuracy',
            'is_active' => true,
            'created_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->copyAssignments('Scan Outbound', 'Courier Sorting Accuracy', $packingInputId);
        $this->copyAssignments('Admin Outbound', 'Resi Import Timeliness', $duplicateAccuracyId);
        $this->copyAssignments('Admin Outbound', 'Delivery Note Completion', $duplicateAccuracyId);

        DB::table('kpi_definitions')
            ->where(function ($query) {
                $query->where(fn ($q) => $q->where('role_name', 'Scan Outbound')->where('metric_name', 'Courier Sorting Accuracy'))
                    ->orWhere(fn ($q) => $q->where('role_name', 'Admin Outbound')->whereIn('metric_name', [
                        'Resi Import Timeliness',
                        'Delivery Note Completion',
                    ]));
            })
            ->delete();
    }

    public function down(): void
    {
        if (!Schema::hasTable('kpi_definitions')) {
            return;
        }

        DB::table('kpi_definitions')
            ->where(function ($query) {
                $query->where(fn ($q) => $q->where('role_name', 'Scan Outbound')->where('metric_name', 'Packing Employee Input Completion'))
                    ->orWhere(fn ($q) => $q->where('role_name', 'Admin Outbound')->where('metric_name', 'Resi Import Duplicate Accuracy'));
            })
            ->delete();
    }

    private function upsertDefinition(array $data): int
    {
        DB::table('kpi_definitions')->updateOrInsert(
            ['role_name' => $data['role_name'], 'metric_name' => $data['metric_name']],
            $data
        );

        return (int) DB::table('kpi_definitions')
            ->where('role_name', $data['role_name'])
            ->where('metric_name', $data['metric_name'])
            ->value('id');
    }

    private function copyAssignments(string $oldRole, string $oldMetric, int $newDefinitionId): void
    {
        if (!$newDefinitionId || !Schema::hasTable('kpi_employee_assignments')) {
            return;
        }

        $oldDefinitionId = DB::table('kpi_definitions')
            ->where('role_name', $oldRole)
            ->where('metric_name', $oldMetric)
            ->value('id');

        if (!$oldDefinitionId) {
            return;
        }

        $now = now();
        $assignments = DB::table('kpi_employee_assignments')
            ->where('kpi_definition_id', $oldDefinitionId)
            ->get();

        foreach ($assignments as $assignment) {
            $exists = DB::table('kpi_employee_assignments')
                ->where('employee_id', $assignment->employee_id)
                ->where('kpi_definition_id', $newDefinitionId)
                ->where('is_active', true)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('kpi_employee_assignments')->insert([
                'employee_id' => $assignment->employee_id,
                'kpi_definition_id' => $newDefinitionId,
                'effective_from' => $assignment->effective_from,
                'effective_until' => $assignment->effective_until,
                'target_value' => $assignment->target_value,
                'weight' => $assignment->weight,
                'is_active' => $assignment->is_active,
                'created_by' => $assignment->created_by,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function description(string $definition, string $trackingType, string $featureStatus, string $module, string $formula, string $flow, string $note): string
    {
        return implode("\n", [
            $definition,
            'Tipe tracking: '.$trackingType,
            'Status fitur: '.$featureStatus,
            'Modul aplikasi: '.$module,
            'Formula/cara hitung: '.$formula,
            'Alur tracking: '.$flow,
            'Catatan: '.$note,
        ]);
    }
};
