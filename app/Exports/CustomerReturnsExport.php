<?php

namespace App\Exports;

use App\Models\CustomerReturn;
use App\Models\CustomerReturnItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CustomerReturnsExport implements FromCollection, WithHeadings, WithTitle, WithCustomStartCell, ShouldAutoSize, WithStyles, WithEvents
{
    private ?Collection $rows = null;

    public function __construct(private array $filters = [])
    {
    }

    public function title(): string
    {
        return 'Retur Customer';
    }

    public function startCell(): string
    {
        return 'A5';
    }

    public function collection(): Collection
    {
        if ($this->rows !== null) {
            return $this->rows;
        }

        $this->rows = $this->query()->get()->flatMap(function (CustomerReturn $row) {
            $items = $row->items->isNotEmpty() ? $row->items : collect([null]);

            return $items->map(function (?CustomerReturnItem $itemRow) use ($row) {
                $expectedQty = (int) ($itemRow?->expected_qty ?? 0);
                $receivedQty = (int) ($itemRow?->received_qty ?? 0);

                return [
                    $row->code,
                    $row->received_at?->format('Y-m-d H:i') ?? '',
                    $row->resi_no,
                    $row->resi?->kurir?->name ?? '-',
                    $row->order_ref ?? '',
                    $row->resi_id ? 'Resi Ditemukan' : 'Input Manual',
                    $this->statusLabel((string) $row->status),
                    $itemRow?->item?->sku ?? '',
                    $itemRow?->item?->name ?? '',
                    $expectedQty,
                    $receivedQty,
                    (int) ($itemRow?->good_qty ?? 0),
                    (int) ($itemRow?->damaged_qty ?? 0),
                    max($expectedQty - $receivedQty, 0),
                    $row->creator?->name ?? '',
                    $row->inspector?->name ?? '',
                    $row->finalizer?->name ?? '',
                    $row->damagedGood?->code ?? '',
                    $row->note ?? '',
                    $itemRow?->rootCauseLabel() ?? '',
                    $itemRow?->note ?? '',
                ];
            });
        })->values();

        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'Kode Retur',
            'Tanggal Terima',
            'No Resi',
            'Ekspedisi',
            'Order Ref',
            'Status Resi',
            'Status Retur',
            'SKU',
            'Nama Item',
            'Qty Resi',
            'Qty Diterima',
            'Qty Bagus',
            'Qty Rusak',
            'Qty Hilang',
            'Dibuat Oleh',
            'Inspector',
            'PIC Finalisasi',
            'Kode Barang Rusak',
            'Catatan Dokumen',
            'Penyebab Retur',
            'Catatan Item',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $rowCount = $this->collection()->count();
        $sheet->mergeCells('A1:U1');
        $sheet->mergeCells('A2:U2');
        $sheet->mergeCells('A3:U3');
        $sheet->setCellValue('A1', 'Export Retur Customer');
        $sheet->setCellValue('A2', $this->filterSummary());
        $sheet->setCellValue('A3', 'Total baris item: '.number_format($rowCount, 0, ',', '.'));

        return [
            1 => ['font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '181C32']]],
            2 => ['font' => ['color' => ['rgb' => '7E8299']]],
            3 => ['font' => ['bold' => true, 'color' => ['rgb' => '3F4254']]],
            5 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1B84FF']],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = max(5, 5 + $this->collection()->count());
                $range = 'A5:U'.$lastRow;

                $sheet->freezePane('A6');
                $sheet->setAutoFilter($range);
                $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E4E6EF');
                $sheet->getStyle('A1:U'.$lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle('J6:N'.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle('J6:N'.$lastRow)->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle('S6:U'.$lastRow)->getAlignment()->setWrapText(true);
                $sheet->getStyle('A5:U5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                foreach (['A' => 18, 'B' => 18, 'C' => 22, 'D' => 20, 'E' => 20, 'H' => 18, 'I' => 30, 'S' => 36, 'T' => 22, 'U' => 36] as $column => $width) {
                    $sheet->getColumnDimension($column)->setWidth($width);
                }
            },
        ];
    }

    private function query()
    {
        $query = CustomerReturn::query()
            ->with(['items.item', 'creator', 'inspector', 'finalizer', 'damagedGood', 'resi.kurir'])
            ->orderByDesc('received_at')
            ->orderByDesc('id');

        $status = trim((string) ($this->filters['status'] ?? ''));
        if (in_array($status, [
            CustomerReturn::STATUS_INSPECTED,
            CustomerReturn::STATUS_COMPLETED,
            CustomerReturn::STATUS_NO_RECEIVED,
        ], true)) {
            $query->where('status', $status);
        } elseif ($status !== '') {
            $query->whereRaw('1 = 0');
        }

        $matchState = trim((string) ($this->filters['match_state'] ?? ''));
        if ($matchState === 'matched') {
            $query->whereNotNull('resi_id');
        } elseif ($matchState === 'unmatched') {
            $query->whereNull('resi_id');
        }

        $this->applyDateFilter($query, 'customer_returns.received_at');
        $this->applySearch($query);

        return $query;
    }

    private function applyDateFilter($query, string $column): void
    {
        try {
            if (!empty($this->filters['date_from'])) {
                $query->where($column, '>=', Carbon::parse($this->filters['date_from'])->startOfDay());
            }
            if (!empty($this->filters['date_to'])) {
                $query->where($column, '<=', Carbon::parse($this->filters['date_to'])->endOfDay());
            }
        } catch (\Throwable) {
            // Ignore invalid export date filters and keep the export usable.
        }
    }

    private function applySearch($query): void
    {
        $search = trim((string) ($this->filters['q'] ?? ''));
        if ($search === '') {
            return;
        }

        $exact = filter_var($this->filters['exact'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $operator = $exact ? '=' : 'LIKE';
        $value = $exact ? $search : '%'.$search.'%';

        $query->where(function ($q) use ($operator, $value) {
            $q->where('customer_returns.code', $operator, $value)
                ->orWhere('customer_returns.resi_no', $operator, $value)
                ->orWhere('customer_returns.order_ref', $operator, $value)
                ->orWhere('customer_returns.note', $operator, $value)
                ->orWhereHas('damagedGood', fn ($damagedQ) => $damagedQ->where('code', $operator, $value))
                ->orWhereHas('items', fn ($itemQ) => $itemQ->where('root_cause', $operator, $value))
                ->orWhereHas('items.item', function ($itemQ) use ($operator, $value) {
                    $itemQ->where('sku', $operator, $value)
                        ->orWhere('name', $operator, $value);
                });
        });
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            CustomerReturn::STATUS_COMPLETED => 'Selesai',
            CustomerReturn::STATUS_NO_RECEIVED => 'Tidak Diterima',
            default => 'Belum Finalisasi',
        };
    }

    private function filterSummary(): string
    {
        $parts = [];
        if (!empty($this->filters['q'])) {
            $parts[] = 'Pencarian: '.$this->filters['q'];
        }
        if (!empty($this->filters['status'])) {
            $parts[] = 'Status: '.$this->statusLabel((string) $this->filters['status']);
        }
        if (!empty($this->filters['match_state'])) {
            $parts[] = 'Status Resi: '.($this->filters['match_state'] === 'matched' ? 'Resi Ditemukan' : 'Input Manual');
        }
        if (!empty($this->filters['date_from']) || !empty($this->filters['date_to'])) {
            $parts[] = 'Periode: '.($this->filters['date_from'] ?? '-').' s/d '.($this->filters['date_to'] ?? '-');
        }

        return $parts ? implode(' | ', $parts) : 'Semua data retur customer';
    }
}
