<?php

namespace App\Exports;

use App\Models\InboundItem;
use App\Models\InboundTransaction;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;

class InboundReceiptsDetailSheet extends InboundReceiptsSheet implements FromCollection, WithHeadings, WithTitle, WithCustomStartCell, WithStyles, WithStrictNullComparison, WithEvents
{
    private ?Collection $rows = null;

    public function title(): string
    {
        return 'Detail Item';
    }

    public function headings(): array
    {
        return [
            'No',
            'Kode Penerimaan',
            'Tanggal Transaksi',
            'Status',
            'Supplier',
            'No Surat Jalan',
            'Tanggal Surat Jalan',
            'Gudang',
            'SKU',
            'Nama Barang',
            'Satuan Input',
            'Koli',
            'Qty (Pcs)',
            'Koli Terscan',
            'Qty Terscan',
            'Selisih Qty',
            'Catatan Item',
        ];
    }

    public function collection(): Collection
    {
        if ($this->rows !== null) {
            return $this->rows;
        }

        $number = 0;

        return $this->rows = $this->transactions()->flatMap(function (InboundTransaction $row) use (&$number) {
            $scanMap = $this->scanMap($row);

            return ($row->items ?? collect())->map(function (InboundItem $item) use ($row, $scanMap, &$number) {
                $scan = $scanMap[(int) $item->item_id] ?? ['scanned_qty' => 0, 'scanned_koli' => 0];
                $qty = (int) ($item->qty ?? 0);

                return [
                    ++$number,
                    $row->code,
                    $row->transacted_at?->format('d/m/Y H:i') ?? '',
                    $this->statusLabel($row->status),
                    $row->supplier?->name ?? '',
                    $row->surat_jalan_no ?? '',
                    $row->surat_jalan_at?->format('d/m/Y') ?? '',
                    $this->warehouseLabel($row),
                    $item->item?->sku ?? '',
                    $item->item?->name ?? '',
                    ($item->input_unit ?: 'koli') === 'pcs' ? 'Pcs' : 'Koli',
                    $this->koliOf($item),
                    $qty,
                    (int) $scan['scanned_koli'],
                    (int) $scan['scanned_qty'],
                    (int) $scan['scanned_qty'] - $qty,
                    $item->note ?? '',
                ];
            });
        })->values();
    }

    protected function reportTitle(): string
    {
        return 'Laporan Penerimaan Barang - Detail Item';
    }

    protected function lastColumn(): string
    {
        return 'Q';
    }

    protected function rowCount(): int
    {
        return $this->collection()->count();
    }

    protected function totalLabel(int $rowCount): string
    {
        return 'Total baris item: '.number_format($rowCount, 0, ',', '.');
    }

    protected function numericColumns(): array
    {
        return ['L', 'M', 'N', 'O', 'P'];
    }

    protected function columnWidths(): array
    {
        return [
            'A' => 6,
            'B' => 24,
            'C' => 18,
            'D' => 20,
            'E' => 26,
            'F' => 22,
            'G' => 18,
            'H' => 20,
            'I' => 18,
            'J' => 34,
            'K' => 13,
            'L' => 10,
            'M' => 12,
            'N' => 14,
            'O' => 14,
            'P' => 13,
            'Q' => 34,
        ];
    }
}
