<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class EmployeeSchedulesTemplateExport implements FromCollection, WithHeadings, ShouldAutoSize, WithEvents
{
    public function collection(): Collection
    {
        return new Collection([
            [
                'K0001',
                now()->toDateString(),
                'work',
                'Shift Pagi',
                'Jadwal masuk normal',
            ],
            [
                'K0002',
                now()->addDay()->toDateString(),
                'day_off',
                '',
                'Libur mingguan',
            ],
            [
                'K0003',
                now()->addDays(2)->toDateString(),
                'leave',
                '',
                'Cuti/Izin',
            ],
        ]);
    }

    public function headings(): array
    {
        return [
            'employee_code',
            'schedule_date',
            'schedule_type',
            'shift',
            'note',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = 4;
                $range = 'A1:E'.$lastRow;

                $sheet->freezePane('A2');
                $sheet->setAutoFilter($range);
                $sheet->getStyle('A1:E1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
                $sheet->getStyle('A1:E1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1B84FF');
                $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E4E6EF');
                $sheet->getStyle($range)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getComment('A1')->getText()->createTextRun('Isi kode karyawan aktif, contoh K0001.');
                $sheet->getComment('B1')->getText()->createTextRun('Tanggal wajib format YYYY-MM-DD dan tidak boleh tanggal lampau.');
                $sheet->getComment('C1')->getText()->createTextRun('Pilihan: work, day_off, holiday, leave.');
                $sheet->getComment('D1')->getText()->createTextRun('Wajib diisi jika schedule_type = work. Isi nama shift sesuai master shift.');
            },
        ];
    }
}
