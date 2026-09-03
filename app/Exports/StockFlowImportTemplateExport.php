<?php

namespace App\Exports;

use App\Models\Warehouse;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class StockFlowImportTemplateExport implements WithMultipleSheets
{
    public const OUTBOUND_MANUAL = 'outbound_manual';

    public const INBOUND_RETURN = 'inbound_return';

    public const INBOUND_RETURN_ITEMS = 'inbound_return_items';

    public function __construct(private readonly string $profile) {}

    public function sheets(): array
    {
        $definition = $this->definition();

        return [
            new StockFlowImportDataSheet($definition),
            new StockFlowImportGuideSheet($definition),
            new StockFlowImportReferenceSheet($definition),
        ];
    }

    public function definition(): array
    {
        $definition = match ($this->profile) {
            self::OUTBOUND_MANUAL => $this->outboundManualDefinition(),
            self::INBOUND_RETURN => $this->inboundReturnDefinition(false),
            self::INBOUND_RETURN_ITEMS => $this->inboundReturnDefinition(true),
            default => throw new \InvalidArgumentException('Profil template import tidak dikenal.'),
        };

        $definition['profile'] = $this->profile;
        $definition['warehouse_codes'] = Warehouse::query()
            ->orderBy('name')
            ->pluck('code')
            ->map(fn ($code) => trim((string) $code))
            ->filter()
            ->values()
            ->all();

        return $definition;
    }

    private function outboundManualDefinition(): array
    {
        return [
            'title' => 'Template Import Outbound Manual',
            'subtitle' => 'Isi sheet Data Import. Jangan mengubah nama header pada baris pertama.',
            'headings' => [
                'sku', 'qty', 'koli', 'warehouse', 'ref_no', 'surat_jalan_no', 'surat_jalan_at',
                'recipient_name', 'recipient_phone', 'recipient_address', 'note', 'item_note', 'transacted_at',
            ],
            'widths' => [18, 12, 12, 22, 22, 24, 18, 26, 20, 38, 34, 34, 22],
            'text_columns' => ['A', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M'],
            'field_guides' => [
                ['sku', 'WAJIB', 'Harus sama dengan SKU pada master item. Huruf besar/kecil ditoleransi.'],
                ['qty', 'KONDISIONAL', 'Wajib untuk gudang selain Gudang Besar. Boleh dikosongkan jika memakai koli di Gudang Besar.'],
                ['koli', 'KONDISIONAL', 'Wajib untuk Gudang Besar. Qty dihitung dari koli × isi per koli master item.'],
                ['warehouse', 'OPSIONAL', 'Isi kode/nama gudang. Jika kosong, sistem memakai Gudang Display.'],
                ['ref_no', 'OPSIONAL', 'Nomor referensi. Baris dengan referensi, surat jalan, gudang, dan penerima yang sama digabung menjadi satu transaksi.'],
                ['surat_jalan_no', 'OPSIONAL', 'Nomor surat jalan. Sistem membuat nomor otomatis jika kosong.'],
                ['surat_jalan_at', 'OPSIONAL', 'Tanggal surat jalan dengan format YYYY-MM-DD.'],
                ['recipient_name', 'OPSIONAL', 'Nama penerima barang, maksimal 150 karakter.'],
                ['recipient_phone', 'OPSIONAL', 'Nomor telepon/kontak penerima, maksimal 50 karakter.'],
                ['recipient_address', 'OPSIONAL', 'Alamat penerima, maksimal 1.000 karakter.'],
                ['note', 'OPSIONAL', 'Catatan transaksi/dokumen.'],
                ['item_note', 'OPSIONAL', 'Catatan khusus untuk baris item.'],
                ['transacted_at', 'OPSIONAL', 'Waktu transaksi format YYYY-MM-DD HH:MM. Jika kosong menggunakan waktu import.'],
            ],
            'rules' => [
                'File yang diterima: XLSX/XLS, maksimal 5 MB.',
                'Stok pada gudang asal harus mencukupi. Jika tidak, seluruh import dibatalkan.',
                'Gudang Besar wajib menggunakan nilai koli yang konsisten dengan isi per koli pada master item.',
                'Baris SKU yang sama dalam satu dokumen akan dijumlahkan otomatis.',
                'Gunakan ref_no atau surat_jalan_no berbeda untuk membuat transaksi yang berbeda.',
                'Contoh berada di sheet Contoh & Panduan dan tidak ikut diimport.',
            ],
            'examples' => [
                ['SKU-001', '', 2, 'GUDANG_BESAR', 'MNL-001', 'SJ-MNL-001', '2026-09-03', 'Budi', '08123456789', 'Jakarta', 'Kirim reguler', 'Pastikan segel utuh', '2026-09-03 10:30'],
                ['SKU-002', '', 1, 'GUDANG_BESAR', 'MNL-001', 'SJ-MNL-001', '2026-09-03', 'Budi', '08123456789', 'Jakarta', 'Kirim reguler', '', '2026-09-03 10:30'],
                ['SKU-003', 5, '', 'GUDANG_DISPLAY', 'MNL-002', '', '', 'Siti', '', 'Bandung', '', 'Input satuan PCS', ''],
            ],
            'validation_column' => 'D',
            'validation_values' => 'warehouse_codes',
            'reference_item_type' => 'all',
        ];
    }

    private function inboundReturnDefinition(bool $itemsOnly): array
    {
        $headings = $itemsOnly
            ? ['sku', 'qty', 'koli', 'input_unit', 'item_note']
            : ['sku', 'qty', 'koli', 'input_unit', 'ref_no', 'surat_jalan_no', 'surat_jalan_at', 'note', 'item_note', 'transacted_at'];

        return [
            'title' => $itemsOnly ? 'Template Import Item Retur Inbound' : 'Template Import Retur Inbound',
            'subtitle' => $itemsOnly
                ? 'Template ini hanya mengisi item pada form aktif. Pilih gudang tujuan sebelum import.'
                : 'Pilih gudang tujuan pada modal import, lalu isi sheet Data Import.',
            'headings' => $headings,
            'widths' => $itemsOnly
                ? [18, 12, 12, 16, 36]
                : [18, 12, 12, 16, 22, 24, 18, 34, 34, 22],
            'text_columns' => $itemsOnly ? ['A', 'D', 'E'] : ['A', 'D', 'E', 'F', 'G', 'H', 'I', 'J'],
            'field_guides' => array_values(array_filter([
                ['sku', 'WAJIB', 'Harus sama dengan SKU single pada master item. Huruf besar/kecil ditoleransi.'],
                ['qty', 'KONDISIONAL', 'Wajib untuk input_unit PCS. Untuk Koli boleh diisi jika sesuai konversi master.'],
                ['koli', 'KONDISIONAL', 'Isi jumlah Koli. Sistem menghitung qty dari isi per koli master jika qty kosong.'],
                ['input_unit', 'OPSIONAL', 'Isi koli atau pcs. Jika kosong dianggap koli. PCS hanya untuk Gudang Display/Gudang Rusak.'],
                $itemsOnly ? null : ['ref_no', 'OPSIONAL', 'Nomor referensi untuk mengelompokkan baris menjadi satu transaksi.'],
                $itemsOnly ? null : ['surat_jalan_no', 'OPSIONAL', 'Nomor referensi retur. Sistem membuat nomor otomatis jika kosong.'],
                $itemsOnly ? null : ['surat_jalan_at', 'OPSIONAL', 'Tanggal retur format YYYY-MM-DD.'],
                $itemsOnly ? null : ['note', 'OPSIONAL', 'Catatan transaksi/dokumen retur.'],
                ['item_note', 'OPSIONAL', 'Catatan khusus untuk item.'],
                $itemsOnly ? null : ['transacted_at', 'OPSIONAL', 'Waktu transaksi format YYYY-MM-DD HH:MM. Jika kosong menggunakan waktu import.'],
            ])),
            'rules' => array_values(array_filter([
                'File yang diterima: XLSX/XLS, maksimal 5 MB.',
                'Gudang tujuan wajib dipilih pada form/modal, bukan ditulis di file.',
                'Input PCS hanya diperbolehkan untuk Gudang Display atau Gudang Rusak: isi qty, kosongkan koli, dan set input_unit=pcs.',
                'Untuk Gudang Besar gunakan input_unit=koli. Nilai qty dan koli harus sesuai isi per koli master item.',
                'SKU bundle tidak dapat digunakan karena inbound memproses stok fisik.',
                'Baris SKU yang sama dengan satuan yang sama akan dijumlahkan otomatis.',
                $itemsOnly
                    ? 'Import item mengganti daftar item yang sedang ada pada form setelah konfirmasi.'
                    : 'Baris dengan ref_no, surat_jalan_no, dan transacted_at yang sama digabung menjadi satu transaksi.',
                'Contoh berada di sheet Contoh & Panduan dan tidak ikut diimport.',
            ])),
            'examples' => $itemsOnly
                ? [
                    ['SKU-001', '', 2, 'koli', 'Kemasan perlu diperiksa'],
                    ['SKU-002', 5, '', 'pcs', 'Barang satuan'],
                ]
                : [
                    ['SKU-001', '', 2, 'koli', 'RET-IN-001', 'REF-RET-001', '2026-09-03', 'Retur toko', 'Kemasan penyok', '2026-09-03 09:00'],
                    ['SKU-002', '', 1, 'koli', 'RET-IN-001', 'REF-RET-001', '2026-09-03', 'Retur toko', '', '2026-09-03 09:00'],
                    ['SKU-003', 5, '', 'pcs', 'RET-IN-002', '', '', 'Retur satuan', '', ''],
                ],
            'validation_column' => 'D',
            'validation_values' => ['koli', 'pcs'],
            'reference_item_type' => 'single',
        ];
    }
}
