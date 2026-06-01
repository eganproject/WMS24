<?php

namespace Tests\Feature\Admin;

use App\Exports\WorkShiftsExport;
use App\Models\User;
use App\Models\WorkShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class AttendanceWorkShiftsImportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_shift_export_uses_import_format(): void
    {
        $shift = WorkShift::create([
            'name' => 'Shift Pagi',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'break_start_time' => '12:00',
            'break_end_time' => '13:00',
            'late_tolerance_minutes' => 5,
            'checkout_tolerance_minutes' => 10,
            'overtime_start_after_minutes' => 30,
            'minimum_overtime_minutes' => 45,
            'crosses_midnight' => false,
            'is_active' => true,
        ]);

        $export = new WorkShiftsExport();

        $this->assertSame([
            'name',
            'start_time',
            'end_time',
            'break_start_time',
            'break_end_time',
            'late_tolerance_minutes',
            'checkout_tolerance_minutes',
            'overtime_start_after_minutes',
            'minimum_overtime_minutes',
            'crosses_midnight',
            'is_active',
        ], $export->headings());

        $this->assertSame([
            'Shift Pagi',
            '08:00',
            '17:00',
            '12:00',
            '13:00',
            5,
            10,
            30,
            45,
            'no',
            'active',
        ], $export->map($shift->fresh()));
    }

    public function test_shift_import_accepts_export_format(): void
    {
        $file = $this->makeExcelUpload([
            ['name', 'start_time', 'end_time', 'break_start_time', 'break_end_time', 'late_tolerance_minutes', 'checkout_tolerance_minutes', 'overtime_start_after_minutes', 'minimum_overtime_minutes', 'crosses_midnight', 'is_active'],
            ['Shift Malam', '22:00', '06:00', '02:00', '03:00', 5, 10, 30, 45, 'yes', 'active'],
        ]);

        $this->actingAs(User::factory()->create())
            ->withoutMiddleware()
            ->post(route('admin.attendance.shifts.import'), [
                'file' => $file,
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('created', 1)
            ->assertJsonPath('updated', 0);

        $this->assertDatabaseHas('work_shifts', [
            'name' => 'Shift Malam',
            'start_time' => '22:00',
            'end_time' => '06:00',
            'break_start_time' => '02:00',
            'break_end_time' => '03:00',
            'late_tolerance_minutes' => 5,
            'checkout_tolerance_minutes' => 10,
            'overtime_start_after_minutes' => 30,
            'minimum_overtime_minutes' => 45,
            'crosses_midnight' => true,
            'is_active' => true,
        ]);
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

        $path = tempnam(sys_get_temp_dir(), 'attendance-shifts-');
        $writer = new Xlsx($spreadsheet);
        $writer->save($path);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return new UploadedFile(
            $path,
            'attendance-shifts.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }
}
