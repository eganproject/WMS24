<?php

namespace Tests\Feature\Admin;

use App\Exports\EmployeesExport;
use App\Models\Area;
use App\Models\Employee;
use App\Models\EmployeePosition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class AttendanceEmployeesImportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_import_accepts_indonesian_inactive_status(): void
    {
        $position = EmployeePosition::create(['name' => 'Picker', 'is_active' => true]);
        $area = Area::create(['code' => 'GDG', 'name' => 'Gudang', 'is_active' => true]);
        $login = User::factory()->create(['email' => 'budi@example.com']);
        $file = $this->makeExcelUpload([
            ['employee_code', 'name', 'phone', 'employment_status', 'position_id', 'area', 'user_email', 'join_date'],
            ['K9001', 'Budi Import', '081234567890', 'Nonaktif', $position->id, $area->code, $login->email, '2026-05-30'],
        ]);

        $this->actingAs(User::factory()->create())
            ->withoutMiddleware()
            ->post(route('admin.attendance.employees.import'), [
                'file' => $file,
                'mode' => 'create_only',
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('created', 1)
            ->assertJsonPath('updated', 0);

        $employee = Employee::where('employee_code', 'K9001')->first();

        $this->assertNotNull($employee);
        $this->assertSame(Employee::STATUS_INACTIVE, $employee->employment_status);
        $this->assertSame($position->id, $employee->position_id);
        $this->assertSame($area->id, $employee->area_id);
        $this->assertSame($login->id, $employee->user_id);
    }

    public function test_employee_export_uses_employee_template_format(): void
    {
        $position = EmployeePosition::create(['name' => 'Picker', 'is_active' => true]);
        $area = Area::create(['code' => 'GDG', 'name' => 'Gudang', 'is_active' => true]);
        $login = User::factory()->create(['email' => 'sari@example.com']);
        $employee = Employee::create([
            'employee_code' => 'K9002',
            'name' => 'Sari Export',
            'phone' => '0899999999',
            'employment_status' => Employee::STATUS_ACTIVE,
            'position_id' => $position->id,
            'area_id' => $area->id,
            'user_id' => $login->id,
            'join_date' => '2026-05-30',
        ]);

        $export = new EmployeesExport();

        $this->assertSame([
            'employee_code',
            'name',
            'phone',
            'employment_status',
            'position',
            'position_id',
            'area',
            'area_id',
            'user_email',
            'user_id',
            'join_date',
        ], $export->headings());

        $this->assertSame([
            'K9002',
            'Sari Export',
            '0899999999',
            Employee::STATUS_ACTIVE,
            'Picker',
            $position->id,
            'GDG',
            $area->id,
            'sari@example.com',
            $login->id,
            '2026-05-30',
        ], $export->map($employee->fresh(['area', 'positionRelation', 'user'])));
    }

    /**
     * @param array<int,array<int|string|null>> $rows
     */
    private function makeExcelUpload(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($rows as $rowIndex => $row) {
            foreach (array_values($row) as $columnIndex => $value) {
                $sheet->setCellValueByColumnAndRow($columnIndex + 1, $rowIndex + 1, $value);
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'attendance-employees-');
        $writer = new Xlsx($spreadsheet);
        $writer->save($path);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return new UploadedFile(
            $path,
            'attendance-employees.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }
}
