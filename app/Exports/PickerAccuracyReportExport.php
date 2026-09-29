<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class PickerAccuracyReportExport implements WithMultipleSheets
{
    public function __construct(private array $report)
    {
    }

    public function sheets(): array
    {
        return [
            new PickerAccuracyReportSummarySheet($this->report),
            new PickerAccuracyReportTableSheet(
                'SKU Tertukar',
                ['SKU Seharusnya', 'Nama SKU Seharusnya', 'Lokasi SKU Seharusnya', 'SKU Terambil', 'Nama SKU Terambil', 'Lokasi SKU Terambil', 'Kejadian', 'Jumlah Picker', 'Terakhir'],
                array_map(fn (array $row) => [
                    $row['expected_sku'], $row['expected_sku_name'], $row['expected_sku_location'],
                    $row['picked_sku'], $row['picked_sku_name'], $row['picked_sku_location'],
                    $row['occurrences'], $row['pickers'], $row['last_occurred_at'],
                ], $this->report['sku_pairs']),
                [0, 1, 2, 3, 4, 5]
            ),
            new PickerAccuracyReportTableSheet(
                'Kategori Alasan',
                ['Aksi', 'Kategori', 'Kesalahan Picker', 'Kejadian', 'Jumlah Picker'],
                array_map(fn (array $row) => [
                    $row['event_label'], $row['reason_label'], $row['is_picker_fault'] ? 'Ya' : 'Tidak',
                    $row['occurrences'], $row['pickers'],
                ], $this->report['reasons']),
                [0, 1]
            ),
            new PickerAccuracyReportTableSheet(
                'Detail Kejadian',
                ['Waktu', 'Jenis', 'Kesalahan Picker', 'Picker', 'Operator QC', 'ID Pesanan', 'No. Resi', 'Kode Scan', 'SKU Discan', 'Lokasi SKU Discan', 'SKU Seharusnya', 'Lokasi SKU Seharusnya', 'Qty', 'Qty Target', 'Qty Sudah Scan', 'Kategori', 'Alasan'],
                array_map(fn (array $row) => [
                    $row['occurred_at'], $row['event_label'], $row['is_picker_fault'] ? 'Ya' : 'Tidak', $row['picker'], $row['qc_operator'],
                    $row['id_pesanan'], $row['no_resi'], $row['scan_code'], $row['sku'], $row['sku_location'],
                    $row['expected_sku'], $row['expected_sku_location'], $row['qty'], $row['expected_qty'], $row['scanned_qty'],
                    $row['reason_label'], $row['reason'],
                ], $this->report['events']),
                [3, 4, 5, 6, 7, 8, 9, 10, 11, 15, 16]
            ),
        ];
    }
}
