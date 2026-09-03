<?php

namespace App\Exports;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithCustomChunkSize;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OutboundManualDocumentsSheet extends OutboundManualTableSheet implements FromQuery, WithMapping, WithHeadings, WithTitle, WithCustomStartCell, WithStyles, WithEvents, WithStrictNullComparison, WithCustomValueBinder, WithCustomChunkSize
{
    private int $number = 0;

    public function title(): string
    {
        return 'Daftar Dokumen';
    }

    public function query()
    {
        return $this->report->documentRowsQuery();
    }

    public function headings(): array
    {
        return [
            'No', 'Kode Outbound', 'Tanggal Transaksi', 'Status', 'Kode Gudang', 'Gudang', 'Penerima', 'Telepon', 'Alamat',
            'No Surat Jalan', 'Tanggal Surat Jalan', 'Referensi', 'Jumlah SKU', 'Qty Rencana', 'Target QC', 'Qty Scan',
            'Sisa QC', 'Progres QC', 'Submit By', 'Disetujui Pada', 'Catatan',
        ];
    }

    public function map($row): array
    {
        $expected = (int) $row->expected_qty;
        $scanned = (int) $row->scanned_qty;
        return [
            ++$this->number,
            $row->code ?? '',
            $row->transacted_at ? Date::dateTimeToExcel(Carbon::parse($row->transacted_at)) : null,
            $this->report->statusLabel($row->status),
            $row->warehouse_code ?? '',
            $row->warehouse_name ?? '-',
            $row->recipient_name ?? '',
            $row->recipient_phone ?? '',
            $row->recipient_address ?? '',
            $row->surat_jalan_no ?? '',
            $row->surat_jalan_at ? Date::dateTimeToExcel(Carbon::parse($row->surat_jalan_at)) : null,
            $row->ref_no ?? '',
            (int) $row->sku_count,
            (int) $row->planned_qty,
            $expected,
            $scanned,
            max(0, $expected - $scanned),
            $expected > 0 ? $scanned / $expected : 0,
            $row->creator_name ?? '-',
            $row->approved_at ? Date::dateTimeToExcel(Carbon::parse($row->approved_at)) : null,
            $row->note ?? '',
        ];
    }

    public function collection(): Collection
    {
        $this->number = 0;
        return $this->report->documentRowsQuery()->get()->map(fn ($row) => $this->map($row));
    }

    public function chunkSize(): int
    {
        return 2000;
    }

    protected function reportTitle(): string
    {
        return 'Laporan Outbound Manual - Daftar Dokumen';
    }

    protected function lastColumn(): string
    {
        return 'U';
    }

    protected function columnWidths(): array
    {
        return [
            'A' => 7, 'B' => 24, 'C' => 20, 'D' => 21, 'E' => 15, 'F' => 23, 'G' => 25, 'H' => 18,
            'I' => 42, 'J' => 24, 'K' => 18, 'L' => 22, 'M' => 12, 'N' => 14, 'O' => 13, 'P' => 13,
            'Q' => 12, 'R' => 13, 'S' => 22, 'T' => 20, 'U' => 40,
        ];
    }

    protected function numericColumns(): array
    {
        return ['A', 'M', 'N', 'O', 'P', 'Q'];
    }

    protected function rowCount(): int
    {
        return $this->report->documentCount();
    }

    protected function formatBody(Worksheet $sheet, int $lastRow): void
    {
        if ($lastRow <= self::HEADER_ROW) {
            return;
        }
        $first = self::HEADER_ROW + 1;
        $sheet->getStyle('G'.$first.':I'.$lastRow)->getAlignment()->setWrapText(true);
        $sheet->getStyle('U'.$first.':U'.$lastRow)->getAlignment()->setWrapText(true);
        $sheet->getStyle('C'.$first.':C'.$lastRow)->getNumberFormat()->setFormatCode('dd/mm/yyyy hh:mm');
        $sheet->getStyle('K'.$first.':K'.$lastRow)->getNumberFormat()->setFormatCode('dd/mm/yyyy');
        $sheet->getStyle('R'.$first.':R'.$lastRow)->getNumberFormat()->setFormatCode('0.00%');
        $sheet->getStyle('T'.$first.':T'.$lastRow)->getNumberFormat()->setFormatCode('dd/mm/yyyy hh:mm');

        $remaining = new Conditional();
        $remaining->setConditionType(Conditional::CONDITION_CELLIS)->setOperatorType(Conditional::OPERATOR_GREATERTHAN)->addCondition('0');
        $remaining->getStyle()->getFont()->setBold(true)->getColor()->setRGB('B54708');
        $remaining->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEF0C7');
        $sheet->getStyle('Q'.$first.':Q'.$lastRow)->setConditionalStyles([$remaining]);
    }
}
