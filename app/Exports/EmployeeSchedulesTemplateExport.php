<?php

namespace App\Exports;

use App\Models\Employee;
use App\Models\WeeklyScheduleTemplate;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class EmployeeSchedulesTemplateExport implements FromCollection, WithHeadings, ShouldAutoSize, WithEvents
{
    public function collection(): Collection
    {
        $employees = Employee::query()
            ->active()
            ->orderBy('name')
            ->get(['employee_code', 'name']);

        if ($employees->isEmpty()) {
            $employees = collect([
                (object) ['employee_code' => 'K0001', 'name' => 'Contoh Karyawan 1'],
                (object) ['employee_code' => 'K0002', 'name' => 'Contoh Karyawan 2'],
            ]);
        }

        $templateName = WeeklyScheduleTemplate::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->value('name') ?? 'Nama Template Jadwal';

        return $employees->map(function ($employee) use ($templateName) {
            return [
                $employee->employee_code,
                $employee->name,
                now()->toDateString(),
                now()->endOfMonth()->toDateString(),
                $templateName,
                '',
            ];
        });
    }

    public function headings(): array
    {
        return [
            'employee_code',
            'nama_karyawan',
            'berlaku_dari',
            'berlaku_sampai',
            'template_jadwal',
            'note',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastColumn = Coordinate::stringFromColumnIndex(count($this->headings()));
                $lastRow = max(2, $sheet->getHighestRow());
                $range = 'A1:'.$lastColumn.$lastRow;

                $sheet->freezePane('A2');
                $sheet->setAutoFilter($range);
                $sheet->getStyle('A1:'.$lastColumn.'1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
                $sheet->getStyle('A1:'.$lastColumn.'1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1B84FF');
                $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E4E6EF');
                $sheet->getStyle($range)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle($range)->getAlignment()->setWrapText(true);
                $sheet->getComment('A1')->getText()->createTextRun('Isi kode karyawan aktif, contoh K0001.');
                $sheet->getComment('B1')->getText()->createTextRun('Nama hanya untuk bantuan baca. Import tetap memakai employee_code jika diisi.');
                $sheet->getComment('C1')->getText()->createTextRun('Tanggal mulai berlaku. Format disarankan YYYY-MM-DD atau DD/MM/YYYY. Tidak boleh tanggal lampau.');
                $sheet->getComment('D1')->getText()->createTextRun('Tanggal akhir berlaku. Maksimal rentang 366 hari dari berlaku_dari.');
                $sheet->getComment('E1')->getText()->createTextRun('Isi nama template jadwal persis seperti master Template Jadwal, contoh: Libur Jumat (MASUK JAM 10).');
                $sheet->getComment('F1')->getText()->createTextRun('Opsional. Catatan ini akan ditulis ke jadwal yang dibuat dari import.');

                $sheet->getStyle('C2:D'.$lastRow)->getNumberFormat()->setFormatCode('dd/mm/yyyy');
                $sheet->getColumnDimension('A')->setWidth(16);
                $sheet->getColumnDimension('B')->setWidth(28);
                $sheet->getColumnDimension('C')->setWidth(16);
                $sheet->getColumnDimension('D')->setWidth(16);
                $sheet->getColumnDimension('E')->setWidth(36);
                $sheet->getColumnDimension('F')->setWidth(28);
            },
        ];
    }
}
