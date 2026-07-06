<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class KpiEmployeeAssignmentSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('kpi_definitions') || !Schema::hasTable('kpi_employee_assignments') || !Schema::hasTable('employees')) {
            return;
        }

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

        $effectiveFrom = now()->startOfMonth()->toDateString();
        $now = now();
        $hasPositionColumn = Schema::hasColumn('kpi_definitions', 'employee_position_id');

        foreach ($rolePositionKeywords as $roleName => $keywords) {
            $definitionSelect = $hasPositionColumn ? ['id', 'employee_position_id'] : ['id'];

            $definitions = DB::table('kpi_definitions')
                ->where('role_name', $roleName)
                ->where('is_active', true)
                ->when($hasPositionColumn, fn ($query) => $query->orWhere(function ($orQuery) use ($keywords) {
                    $orQuery->where('kpi_definitions.is_active', true)
                        ->whereExists(function ($exists) use ($keywords) {
                            $exists->select(DB::raw(1))
                                ->from('employee_positions')
                                ->whereColumn('employee_positions.id', 'kpi_definitions.employee_position_id')
                                ->where(function ($positionQuery) use ($keywords) {
                                    foreach ($keywords as $keyword) {
                                        $positionQuery->orWhereRaw("LOWER(employee_positions.name) LIKE ?", ['%'.$keyword.'%']);
                                    }
                                });
                        });
                }))
                ->get($definitionSelect);

            if ($definitions->isEmpty()) {
                continue;
            }

            $employeeIds = $definitions->pluck('employee_position_id')->filter()->isNotEmpty()
                ? $this->employeeIdsForPositionIds($definitions->pluck('employee_position_id')->filter()->unique()->values())
                : $this->employeeIdsForKeywords($keywords);

            if ($employeeIds->isEmpty()) {
                continue;
            }

            foreach ($employeeIds as $employeeId) {
                foreach ($definitions->pluck('id') as $definitionId) {
                    $exists = DB::table('kpi_employee_assignments')
                        ->where('employee_id', $employeeId)
                        ->where('kpi_definition_id', $definitionId)
                        ->where('is_active', true)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    DB::table('kpi_employee_assignments')->insert([
                        'employee_id' => $employeeId,
                        'kpi_definition_id' => $definitionId,
                        'effective_from' => $effectiveFrom,
                        'effective_until' => null,
                        'target_value' => null,
                        'weight' => null,
                        'is_active' => true,
                        'created_by' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    private function employeeIdsForKeywords(array $keywords)
    {
        $query = DB::table('employees')
            ->leftJoin('employee_positions', 'employee_positions.id', '=', 'employees.position_id')
            ->where('employees.employment_status', 'active')
            ->where(function ($employeeQuery) use ($keywords) {
                foreach ($keywords as $keyword) {
                    $employeeQuery
                        ->orWhereRaw("LOWER(COALESCE(employee_positions.name, '')) LIKE ?", ['%'.$keyword.'%'])
                        ->orWhereRaw("LOWER(COALESCE(employees.position, '')) LIKE ?", ['%'.$keyword.'%']);
                }
            });

        return $query->distinct()->pluck('employees.id');
    }

    private function employeeIdsForPositionIds($positionIds)
    {
        return DB::table('employees')
            ->where('employment_status', 'active')
            ->whereIn('position_id', $positionIds)
            ->distinct()
            ->pluck('id');
    }
}
