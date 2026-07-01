<?php

namespace App\Exports;

use App\Models\Employee;
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
    private array $dates;
    private array $guideHeadings = [
        'panduan_pengisian',
        'contoh_masuk',
        'contoh_libur',
        'contoh_cuti',
        'contoh_libur_perusahaan',
        'contoh_dengan_catatan',
    ];

    public function __construct()
    {
        $this->dates = collect(range(0, 13))
            ->map(fn (int $offset) => now()->addDays($offset)->toDateString())
            ->all();
    }

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

        return $employees->map(function ($employee) {
            $row = [
                $employee->employee_code,
                $employee->name,
            ];

            foreach ($this->dates as $date) {
                $row[] = '';
            }

            $row[] = 'Isi hanya kolom tanggal yang ingin dibuat/diubah. Kosongkan tanggal yang tidak diubah.';
            $row[] = 'Shift Pagi';
            $row[] = 'OFF';
            $row[] = 'LEAVE';
            $row[] = 'HOLIDAY';
            $row[] = 'Shift Pagi | Catatan jadwal';

            return $row;
        });
    }

    public function headings(): array
    {
        return [
            'employee_code',
            'employee_name',
            ...$this->dates,
            ...$this->guideHeadings,
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

                $sheet->freezePane('C2');
                $sheet->setAutoFilter($range);
                $sheet->getStyle('A1:'.$lastColumn.'1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
                $sheet->getStyle('A1:'.$lastColumn.'1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1B84FF');
                $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E4E6EF');
                $sheet->getStyle($range)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle($range)->getAlignment()->setWrapText(true);
                $sheet->getComment('A1')->getText()->createTextRun('Isi kode karyawan aktif, contoh K0001.');
                $sheet->getComment('B1')->getText()->createTextRun('Nama hanya untuk bantuan baca. Import tetap memakai employee_code.');

                for ($columnIndex = 3; $columnIndex <= 2 + count($this->dates); $columnIndex++) {
                    $column = Coordinate::stringFromColumnIndex($columnIndex);
                    $sheet->getComment($column.'1')->getText()->createTextRun(
                        'Isi nama shift untuk jadwal masuk. Isi OFF/day_off/libur untuk libur, LEAVE/cuti/izin untuk cuti, atau HOLIDAY untuk libur perusahaan. Kosongkan jika tidak ingin mengubah tanggal ini.'
                    );
                }

                $guideStartColumn = Coordinate::stringFromColumnIndex(3 + count($this->dates));
                $sheet->getStyle($guideStartColumn.'1:'.$lastColumn.$lastRow)
                    ->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setRGB('FFF8DD');
                $sheet->getComment($guideStartColumn.'1')->getText()->createTextRun('Kolom panduan ini tidak diproses saat import. Boleh dibiarkan apa adanya.');
            },
        ];
    }
}
