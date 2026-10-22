<?php

namespace App\Exports;

use App\Exports\Concerns\StylesReportSheet;
use App\Models\Item;
use App\Models\Warehouse;
use App\Support\BundleService;
use App\Support\WarehouseService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithPreCalculateFormulas;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\DefaultValueBinder;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Export data items dari halaman master items.
 * Kolom B dan C berisi alamat gudang besar (GB) dan gudang kecil/display (GK).
 */
class ItemsExport extends DefaultValueBinder implements FromCollection, WithCustomStartCell, WithCustomValueBinder, WithEvents, WithHeadings, WithPreCalculateFormulas, WithStrictNullComparison, WithTitle
{
    use StylesReportSheet {
        bindValue as protected reportBindValue;
    }

    /** Baris judul grup kolom; header kolom tepat di bawahnya. */
    private const GROUP_ROW = 5;
    private const HEADER_ROW = 6;
    private const LAST_COLUMN = 'Q';

    /** Kolom teks (alamat, SKU, nama) selalu ditulis sebagai teks agar tidak berubah jadi angka/rumus. */
    private const TEXT_COLUMNS = ['B', 'C', 'D', 'E'];

    private const QTY_FORMAT = '#,##0;[Red]-#,##0;0';

    private ?Collection $rows = null;

    public function __construct(private Builder $query, private string $filterSummary = '')
    {
    }

    public function title(): string
    {
        return 'Data Items';
    }

    public function startCell(): string
    {
        return 'A'.self::HEADER_ROW;
    }

    public function headings(): array
    {
        return [
            'ID',
            'Alamat Gudang Besar (GB)',
            'Alamat Gudang Kecil (GK)',
            'SKU',
            'Nama Item',
            'Tipe',
            'Status',
            'Stok (pcs)',
            'Jumlah Koli',
            'Sisa Pcs',
            'Isi per Koli',
            'Safety Stock',
            'Stok (pcs)',
            'Safety Stock',
            'Stok (pcs)',
            'Total Stok Baik (GB + GK)',
            'Total Fisik (Baik + Rusak)',
        ];
    }

    public function collection(): Collection
    {
        if ($this->rows !== null) {
            return $this->rows;
        }

        $mainId = WarehouseService::defaultWarehouseId();
        $displayId = WarehouseService::displayWarehouseId();
        $damagedId = WarehouseService::damagedWarehouseId();

        $items = (clone $this->query)
            ->with(['stocks' => fn ($q) => $q->whereIn('warehouse_id', [$mainId, $displayId, $damagedId])])
            ->get();

        return $this->rows = $items->map(function (Item $item) use ($mainId, $displayId, $damagedId) {
            $stocks = $item->stocks?->keyBy('warehouse_id') ?? collect();
            $isBundle = $item->isBundle();

            $stockMain = $isBundle ? BundleService::virtualAvailableQty($item, $mainId) : (int) ($stocks->get($mainId)?->stock ?? 0);
            $stockDisplay = $isBundle ? BundleService::virtualAvailableQty($item, $displayId) : (int) ($stocks->get($displayId)?->stock ?? 0);
            $stockDamaged = $isBundle ? 0 : (int) ($stocks->get($damagedId)?->stock ?? 0);

            $baseSafety = (int) ($item->safety_stock ?? 0);
            $safetyMain = $stocks->get($mainId)?->safety_stock;
            $safetyDisplay = $stocks->get($displayId)?->safety_stock;

            $koliQty = $isBundle ? 0 : max(0, (int) ($item->koli_qty ?? 0));
            $hasKoli = $koliQty > 0;
            $stockGood = $stockMain + $stockDisplay;

            return [
                (int) $item->id,
                $item->mainAddressLabel() ?: '-',
                $item->displayAddressLabel() ?: '-',
                (string) $item->sku,
                (string) $item->name,
                $isBundle ? 'Bundle' : 'Single',
                ($item->status ?: Item::STATUS_ACTIVE) === Item::STATUS_ACTIVE ? 'Aktif' : 'Nonaktif',
                $stockMain,
                $hasKoli ? intdiv($stockMain, $koliQty) : '-',
                $hasKoli ? $stockMain % $koliQty : '-',
                $hasKoli ? $koliQty : '-',
                $safetyMain !== null ? (int) $safetyMain : $baseSafety,
                $stockDisplay,
                $safetyDisplay !== null ? (int) $safetyDisplay : $baseSafety,
                $stockDamaged,
                $stockGood,
                $isBundle ? $stockGood : $stockGood + $stockDamaged,
            ];
        });
    }

    public function bindValue(Cell $cell, $value)
    {
        if (in_array($cell->getColumn(), self::TEXT_COLUMNS, true) && $cell->getRow() > self::HEADER_ROW) {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return $this->reportBindValue($cell, $value);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $last = self::LAST_COLUMN;
                $group = self::GROUP_ROW;
                $header = self::HEADER_ROW;
                $firstRow = $header + 1;
                $count = $this->collection()->count();
                $lastRow = $header + $count;
                $totalRow = $lastRow + 1;

                $mainName = $this->warehouseName(WarehouseService::defaultWarehouseId(), 'Gudang Besar');
                $displayName = $this->warehouseName(WarehouseService::displayWarehouseId(), 'Gudang Kecil');
                $damagedName = $this->warehouseName(WarehouseService::damagedWarehouseId(), 'Gudang Rusak');

                // Judul & keterangan.
                foreach ([1, 2, 3, 4] as $row) {
                    $sheet->mergeCells("A{$row}:{$last}{$row}");
                }
                $sheet->setCellValue('A1', 'Data Master Items');
                $sheet->setCellValue('A2', $this->filterSummary ?: 'Filter: semua item');
                $sheet->setCellValue('A3', "GB = {$mainName}, GK = {$displayName} (gudang kecil). Alamat 1 / Alamat 2 dipisah tanda \"/\". Stok bundle = stok virtual dari komponennya. \"-\" = tidak ada data. Sel kuning = stok di bawah safety stock.");
                $sheet->setCellValue('A4', sprintf('Total %s item | Diunduh %s', number_format($count, 0, ',', '.'), now()->format('d/m/Y H:i')));
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB('181C32');
                $sheet->getStyle('A2:A3')->getFont()->getColor()->setRGB('7E8299');
                $sheet->getStyle('A4')->getFont()->setBold(true)->getColor()->setRGB('3F4254');

                // Grup kolom: warna berbeda per gudang agar mudah dibedakan.
                $groups = [
                    ['A', 'G', 'Informasi Item', '3F4254'],
                    ['H', 'L', $mainName.' (GB)', '1B84FF'],
                    ['M', 'N', $displayName.' (GK)', '17C653'],
                    ['O', 'O', $damagedName, 'F8285A'],
                    ['P', 'Q', 'Total', '7239EA'],
                ];
                foreach ($groups as [$from, $to, $label, $color]) {
                    if ($from !== $to) {
                        $sheet->mergeCells("{$from}{$group}:{$to}{$group}");
                    }
                    $sheet->setCellValue("{$from}{$group}", $label);
                    $this->styleHeader($sheet, "{$from}{$group}:{$to}{$group}", $color);
                    $this->styleHeader($sheet, "{$from}{$header}:{$to}{$header}", $color);
                }
                $sheet->getRowDimension($group)->setRowHeight(22);
                $sheet->getRowDimension($header)->setRowHeight(36);

                // Baris total stok (ikut filter Excel karena memakai SUBTOTAL).
                $sheet->setCellValue("A{$totalRow}", 'TOTAL');
                $sheet->mergeCells("A{$totalRow}:G{$totalRow}");
                foreach (['H', 'M', 'O', 'P', 'Q'] as $column) {
                    if ($count > 0) {
                        $sheet->setCellValueExplicit("{$column}{$totalRow}", "=SUBTOTAL(109,{$column}{$firstRow}:{$column}{$lastRow})", DataType::TYPE_FORMULA);
                    } else {
                        $sheet->setCellValue("{$column}{$totalRow}", 0);
                    }
                }
                $this->styleTotal($sheet, "A{$totalRow}:{$last}{$totalRow}");

                $sheet->freezePane('F'.$firstRow);
                $sheet->setAutoFilter("A{$header}:{$last}".max($header, $lastRow));
                $this->styleBorders($sheet, "A{$group}:{$last}{$totalRow}");

                $sheet->getStyle("A1:{$last}{$totalRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle("H{$firstRow}:{$last}{$totalRow}")->getNumberFormat()->setFormatCode(self::QTY_FORMAT);
                $sheet->getStyle("H{$firstRow}:{$last}{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle("A{$firstRow}:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("F{$firstRow}:G{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("B{$firstRow}:C{$lastRow}")->getAlignment()->setWrapText(true);
                $sheet->getStyle("D{$firstRow}:D{$lastRow}")->getFont()->setBold(true);
                foreach (['H', 'M', 'P', 'Q'] as $column) {
                    $sheet->getStyle("{$column}{$firstRow}:{$column}{$totalRow}")->getFont()->setBold(true);
                }

                if ($count > 0) {
                    $this->addTextHighlight($sheet, "G{$firstRow}:G{$lastRow}", [
                        ['Aktif', 'E8FFF3', '00875A'],
                        ['Nonaktif', 'F1F1F4', '7E8299'],
                    ]);
                    $this->addTextHighlight($sheet, "F{$firstRow}:F{$lastRow}", [
                        ['Bundle', 'F8F5FF', '7239EA'],
                    ]);
                    // Stok di bawah safety stock ditandai kuning.
                    $this->addBelowSafetyHighlight($sheet, "H{$firstRow}:H{$lastRow}", "AND(L{$firstRow}>0,H{$firstRow}<L{$firstRow})");
                    $this->addBelowSafetyHighlight($sheet, "M{$firstRow}:M{$lastRow}", "AND(N{$firstRow}>0,M{$firstRow}<N{$firstRow})");
                }

                foreach ([
                    'A' => 8, 'B' => 24, 'C' => 24, 'D' => 16, 'E' => 48, 'F' => 10, 'G' => 11,
                    'H' => 12, 'I' => 11, 'J' => 10, 'K' => 11, 'L' => 12,
                    'M' => 12, 'N' => 12, 'O' => 13, 'P' => 15, 'Q' => 15,
                ] as $column => $width) {
                    $sheet->getColumnDimension($column)->setWidth($width);
                }

                $this->setPrintLayout($sheet);
                $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd($group, $header);
            },
        ];
    }

    private function warehouseName(int $id, string $fallback): string
    {
        return (string) (Warehouse::whereKey($id)->value('name') ?: $fallback);
    }

    private function addBelowSafetyHighlight($sheet, string $range, string $formula): void
    {
        $condition = new Conditional;
        $condition->setConditionType(Conditional::CONDITION_EXPRESSION)->addCondition($formula);
        $condition->getStyle()->getFont()->setBold(true)->getColor()->setRGB('946200');
        $condition->getStyle()->getFill()->setFillType(Fill::FILL_SOLID);
        $condition->getStyle()->getFill()->getStartColor()->setRGB('FFF8DD');
        $condition->getStyle()->getFill()->getEndColor()->setRGB('FFF8DD');

        $styles = $sheet->getStyle($range)->getConditionalStyles();
        $styles[] = $condition;
        $sheet->getStyle($range)->setConditionalStyles($styles);
    }
}
