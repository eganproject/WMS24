<?php

namespace Tests\Feature\Admin;

use App\Exports\WeeklyScheduleTemplatesExport;
use App\Models\User;
use App\Models\WeeklyScheduleTemplate;
use App\Models\WeeklyScheduleTemplateDay;
use App\Models\WorkShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class AttendanceScheduleTemplatesImportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_schedule_template_export_uses_import_format(): void
    {
        $shift = WorkShift::create([
            'name' => 'Pagi',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'is_active' => true,
        ]);
        $template = WeeklyScheduleTemplate::create([
            'name' => 'Template Export',
            'is_active' => true,
        ]);
        WeeklyScheduleTemplateDay::create([
            'weekly_schedule_template_id' => $template->id,
            'day_of_week' => 1,
            'schedule_type' => 'work',
            'work_shift_id' => $shift->id,
        ]);

        $export = new WeeklyScheduleTemplatesExport();

        $this->assertSame([
            'template_name',
            'is_active',
            'day_of_week',
            'day_name',
            'schedule_type',
            'shift',
            'work_shift_id',
        ], $export->headings());

        $this->assertSame([
            'Template Export',
            'active',
            1,
            'Senin',
            'work',
            'Pagi',
            $shift->id,
        ], $export->collection()->first());
    }

    public function test_schedule_template_import_accepts_export_format(): void
    {
        $shift = WorkShift::create([
            'name' => 'Pagi',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'is_active' => true,
        ]);
        $file = $this->makeExcelUpload([
            ['template_name', 'is_active', 'day_of_week', 'day_name', 'schedule_type', 'shift', 'work_shift_id'],
            ['Pola Import', 'active', 1, 'Senin', 'work', 'Pagi', ''],
            ['Pola Import', 'active', 2, 'Selasa', 'day_off', '', ''],
        ]);

        $this->actingAs(User::factory()->create())
            ->withoutMiddleware()
            ->post(route('admin.attendance.templates.import'), [
                'file' => $file,
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('created', 1)
            ->assertJsonPath('updated', 0);

        $template = WeeklyScheduleTemplate::where('name', 'Pola Import')->first();

        $this->assertNotNull($template);
        $this->assertDatabaseHas('weekly_schedule_template_days', [
            'weekly_schedule_template_id' => $template->id,
            'day_of_week' => 1,
            'schedule_type' => 'work',
            'work_shift_id' => $shift->id,
        ]);
        $this->assertDatabaseHas('weekly_schedule_template_days', [
            'weekly_schedule_template_id' => $template->id,
            'day_of_week' => 2,
            'schedule_type' => 'day_off',
            'work_shift_id' => null,
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

        $path = tempnam(sys_get_temp_dir(), 'attendance-templates-');
        $writer = new Xlsx($spreadsheet);
        $writer->save($path);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return new UploadedFile(
            $path,
            'attendance-templates.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }
}
