<?php

namespace App\Exports;

use App\Support\WarehouseService;
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

class OutboundManualDetailSheet extends OutboundManualTableSheet implements FromQuery, WithMapping, WithHeadings, WithTitle, WithCustomStartCell, WithStyles, WithEvents, WithStrictNullComparison, WithCustomValueBinder, WithCustomChunkSize
{
    private int $number = 0;

    private ?int $defaultWarehouseId = null;

    public function title(): string
    {
        return 'Detail Item';
    }

    public function query()
    {
        return $this->report->detailRowsQuery();
    }

    public function headings(): array
    {
        return [
            'No', 'Kode Outbound', 'Tanggal Transaksi', 'Status', 'Kode Gudang', 'Gudang', 'Penerima', 'No Surat Jalan',
            'SKU', 'Nama Item', 'Isi per Koli', 'Jumlah Koli', 'Qty Rencana', 'Target QC', 'Qty Scan', 'Sisa QC',
            'Progres QC', 'Catatan Item',
        ];
    }

    public function map($row): array
    {
        $qty = (int) $row->qty;
        $qtyPerKoli = (int) ($row->koli_qty ?? 0);
        $usesKoli = (int) $row->warehouse_id === $this->defaultWarehouseId();
        $koli = $usesKoli && $qtyPerKoli > 0 && $qty > 0 && $qty % $qtyPerKoli === 0
            ? intdiv($qty, $qtyPerKoli)
            : null;
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
            $row->surat_jalan_no ?? '',
            $row->sku ?? '',
            $row->item_name ?? '-',
            $usesKoli && $qtyPerKoli > 0 ? $qtyPerKoli : null,
            $koli,
            $qty,
            $expected,
            $scanned,
            max(0, $expected - $scanned),
            $expected > 0 ? $scanned / $expected : 0,
            $row->item_note ?? '',
        ];
    }

    public function collection(): Collection
    {
        $this->number = 0;
        return $this->report->detailRowsQuery()->get()->map(fn ($row) => $this->map($row));
    }

    public function chunkSize(): int
    {
        return 2000;
    }

    protected function reportTitle(): string
    {
        return 'Laporan Outbound Manual - Detail Item';
    }

    protected function lastColumn(): string
    {
        return 'R';
    }

    protected function columnWidths(): array
    {
        return [
            'A' => 7, 'B' => 24, 'C' => 20, 'D' => 21, 'E' => 15, 'F' => 23, 'G' => 25, 'H' => 24,
            'I' => 20, 'J' => 38, 'K' => 13, 'L' => 13, 'M' => 14, 'N' => 13, 'O' => 13, 'P' => 12,
            'Q' => 13, 'R' => 40,
        ];
    }

    protected function numericColumns(): array
    {
        return ['A', 'K', 'L', 'M', 'N', 'O', 'P'];
    }

    protected function rowCount(): int
    {
        return $this->report->detailCount();
    }

    protected function formatBody(Worksheet $sheet, int $lastRow): void
    {
        if ($lastRow <= self::HEADER_ROW) {
            return;
        }
        $first = self::HEADER_ROW + 1;
        $sheet->getStyle('G'.$first.':J'.$lastRow)->getAlignment()->setWrapText(true);
        $sheet->getStyle('R'.$first.':R'.$lastRow)->getAlignment()->setWrapText(true);
        $sheet->getStyle('C'.$first.':C'.$lastRow)->getNumberFormat()->setFormatCode('dd/mm/yyyy hh:mm');
        $sheet->getStyle('Q'.$first.':Q'.$lastRow)->getNumberFormat()->setFormatCode('0.00%');

        $remaining = new Conditional();
        $remaining->setConditionType(Conditional::CONDITION_CELLIS)->setOperatorType(Conditional::OPERATOR_GREATERTHAN)->addCondition('0');
        $remaining->getStyle()->getFont()->setBold(true)->getColor()->setRGB('B54708');
        $remaining->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEF0C7');
        $sheet->getStyle('P'.$first.':P'.$lastRow)->setConditionalStyles([$remaining]);
    }

    private function defaultWarehouseId(): int
    {
        return $this->defaultWarehouseId ??= WarehouseService::defaultWarehouseId();
    }
}
