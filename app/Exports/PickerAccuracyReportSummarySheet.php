<?php

namespace App\Exports;

use App\Exports\Concerns\SanitizesSpreadsheetText;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class PickerAccuracyReportSummarySheet implements FromArray, WithTitle, WithEvents
{
    use SanitizesSpreadsheetText;

    private const PICKER_HEADER_ROW = 20;

    public function __construct(private array $report)
    {
    }

    public function title(): string
    {
        return 'Ringkasan';
    }

    public function array(): array
    {
        $summary = $this->report['summary'];
        $rows = [
            ['LAPORAN AKURASI PICKER'],
            [$this->periodLabel($this->report['period'])],
            [],
            ['INDIKATOR', 'NILAI'],
            ['Resi dengan atribusi picker', $summary['total_resi']],
            ['Picker aktif', $summary['total_pickers']],
            ['Total qty', $summary['total_qty']],
            ['Akurasi picking (%)', $summary['accuracy_rate']],
            ['First pass rate QC (%)', $summary['first_pass_rate']],
            ['Resi error tertangkap QC', $summary['caught_error_resi']],
            ['Resi error lolos ke pembeli (retur)', $summary['escaped_error_resi']],
            ['Kejadian salah ambil SKU', $summary['wrong_sku_events']],
            ['Kejadian qty berlebih', $summary['over_qty_events']],
            ['Hold/reset karena kesalahan picker', $summary['picker_fault_events']],
            ['Substitusi SKU', $summary['substitution_events']],
            ['Barcode tidak dikenal (master data)', $summary['unknown_barcode_events']],
            ['Resi QC tanpa picker', $summary['unattributed_resi']],
            [],
            ['RINGKASAN PER PICKER (akurasi terendah di atas)'],
            ['Kode', 'Picker', 'Resi', 'Qty', 'Akurasi (%)', 'First Pass (%)', 'Error QC', 'Error Lolos', 'Salah SKU', 'Qty Berlebih', 'Hold/Reset Picker', 'Substitusi', 'Aktivitas Terakhir'],
        ];

        foreach ($this->report['pickers'] as $row) {
            $rows[] = [
                $this->spreadsheetText($row['picker_code']), $this->spreadsheetText($row['picker']), $row['total_resi'], $row['total_qty'],
                $row['accuracy_rate'], $row['first_pass_rate'], $row['caught_error_resi'], $row['escaped_error_resi'],
                $row['wrong_sku'], $row['over_qty'], $row['picker_fault'], $row['substitution'], $row['last_activity'],
            ];
        }

        return $rows;
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $sheet = $event->sheet->getDelegate();
            $header = self::PICKER_HEADER_ROW;
            $lastRow = $header + count($this->report['pickers']);
            $sheet->mergeCells('A1:M1');
            $sheet->mergeCells('A2:M2');
            $sheet->mergeCells('A19:M19');
            $sheet->getStyle('A1:M1')->getFont()->setBold(true)->setSize(16);
            $sheet->getStyle('A2:M2')->getFont()->getColor()->setRGB('7E8299');
            foreach ([4, $header] as $headerRow) {
                $sheet->getStyle("A{$headerRow}:M{$headerRow}")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
                $sheet->getStyle("A{$headerRow}:M{$headerRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1B84FF');
            }
            $sheet->getStyle('A19:M19')->getFont()->setBold(true)->setSize(13);
            $sheet->getStyle('A4:B17')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E4E6EF');
            $sheet->getStyle("A{$header}:M{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E4E6EF');
            $sheet->freezePane('A'.($header + 1));
            $sheet->setAutoFilter("A{$header}:M{$lastRow}");
            $sheet->getStyle("A1:M{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            foreach (range('A', 'M') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }
        }];
    }

    private function periodLabel(array $period): string
    {
        $from = $period['date_from'] ?: 'Semua tanggal';
        $to = $period['date_to'] ?: 'Semua tanggal';
        $picker = $period['picker_name'] ?: 'Semua picker';
        $search = $period['search'] ? ' | Pencarian: '.$period['search'] : '';

        return "Periode mulai QC: {$from} s.d. {$to} | Picker: {$picker}{$search}";
    }
}
