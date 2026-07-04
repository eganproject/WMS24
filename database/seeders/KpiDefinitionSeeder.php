<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class KpiDefinitionSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('kpi_definitions')) {
            return;
        }

        $rows = [
            ['Supervisor', 'Daily Fulfillment Completion', 'Resi aktif yang selesai scan out pada hari/periode yang sama.', '>=', 98, '%', 'daily', 'Otomatis', 'Ada', 'Resi Import, QC, Scan Out, Reports > Scan Out Reports', 'scanned_total / import_total aktif', 'Import resi, proses QC sampai passed, scan out, lalu buka Scan Out Reports untuk membandingkan import_total vs scanned_total.', 'Jika perlu SLA lebih ketat, tambahkan master cut-off per kurir/store.'],
            ['Supervisor', 'QC To Scan Out Flow Rate', 'Persentase paket QC passed yang sudah scan out.', '>=', 95, '%', 'daily', 'Otomatis', 'Ada', 'Outbound > Transit QC, Scan Out History', 'QC passed yang memiliki scan_out / total QC passed', 'QC menyelesaikan resi, scan out memproses paket, Transit QC menunjukkan paket siap scan out dan yang sudah selesai.', 'Bisa ditambah target aging dari completed_at ke scanned_at.'],
            ['Supervisor', 'Operational Exception Rate', 'Rasio exception operasional dari picking, QC, scan out, dan transfer.', '<=', 2, '%', 'daily', 'Semi Otomatis', 'Sebagian Ada', 'Picking Exceptions, QC History, QC Duplicate Attempts, Stock Transfer Report', '(picking exception + duplicate QC + transfer issue lines) / total transaksi terkait', 'Rekap exception dari masing-masing modul lalu gabungkan per tanggal/tim.', 'Saran: buat report exception gabungan lintas modul agar tidak dihitung manual.'],
            ['Stock Controller', 'Stock Opname Accuracy', 'Akurasi opname fisik vs sistem.', '>=', 99.5, '%', 'monthly', 'Otomatis', 'Ada', 'Reports > Stock Opname', '1 - (SKU selisih / total SKU) atau 1 - (SUM(ABS(adjustment)) / SUM(system_qty))', 'Jalankan opname, finalisasi, lalu gunakan summary dan diff SKU pada Stock Opname Report.', 'Gunakan hanya opname status completed.'],
            ['Stock Controller', 'Below Safety Resolution Rate', 'Kasus low stock snapshot yang ditindaklanjuti sampai stok aman.', '>=', 90, '%', 'weekly', 'Semi Otomatis', 'Sebagian Ada', 'Reports > Low Stock Snapshots, Replenishment, Stock Mutations', 'item snapshot yang pada snapshot berikutnya stock >= safety / total item low', 'Buat snapshot low stock rutin, tindaklanjuti replenishment/transfer, lalu bandingkan snapshot berikutnya.', 'Saran: tambah resolved_at/resolved_snapshot_id agar resolusi tidak perlu dihitung komparatif.'],
            ['Stock Controller', 'Safety Stock Coverage', 'Persentase item aktif yang memiliki safety stock.', '>=', 95, '%', 'monthly', 'Otomatis', 'Ada', 'Inventory > Item Stocks', 'item aktif dengan safety_stock > 0 / total item aktif wajib safety', 'Set safety stock pada item/gudang dan export Item Stocks.', 'Saran: tambah field/tag item wajib safety bila tidak semua item wajib safety.'],
            ['Stock Controller', 'Stock Adjustment Control', 'Rasio adjustment manual terhadap total mutasi stok.', '<=', 0, 'target', 'monthly', 'Otomatis', 'Ada', 'Inventory > Stock Adjustments, Stock Mutations', 'total qty adjustment approved / total qty mutasi stok', 'Input adjustment hanya untuk koreksi valid, approve, lalu rekap dari Stock Adjustments dan Stock Mutations.', 'Target awal di Excel masih target bebas. Isi target final dari KPI Master.'],
            ['PIC Transfer Gudang', 'Warehouse Transfer Accuracy', 'Akurasi transfer gudang setelah QC transfer.', '>=', 99, '%', 'weekly', 'Otomatis', 'Ada', 'Inventory > Stock Transfers, Reports > Stock Transfers', 'SUM(qty_ok) / SUM(qty)', 'Buat transfer, scan koli bila QR, lakukan QC transfer, lalu gunakan laporan transfer gudang untuk melihat qty OK/reject/short.', 'Laporan chart-data sudah menyediakan accuracy_rate dan issue_rate.'],
            ['PIC Transfer Gudang', 'Transfer QC Completion SLA', 'Transfer gudang selesai QC dari tanggal transaksi.', '<=', 0, 'SLA', 'daily', 'Otomatis', 'Ada', 'Inventory > Stock Transfers', 'qc_at - transacted_at untuk status completed', 'Buat transfer, lakukan QC transfer sampai completed. Rekap qc_at dan qc_by.', 'Target awal di Excel masih target SLA. Isi target final dari KPI Master.'],
            ['PIC Transfer Gudang', 'QR Traceability Adoption', 'Transfer gudang yang memakai QR inbound dibanding legacy.', '>=', 0, 'target', 'monthly', 'Otomatis', 'Ada', 'Reports > Stock Transfers', 'transfer traceability_mode=qr / total transfer', 'Gunakan mode QR saat transfer dari stok inbound yang punya QR koli. Laporan transfer memberi breakdown QR vs legacy.', 'Target awal di Excel masih target bebas. Isi target final dari KPI Master.'],
            ['Admin Sistem', 'Barcode Miss Resolution Rate', 'Penyelesaian barcode yang gagal dikenali.', '>=', 95, '%', 'weekly', 'Otomatis', 'Ada', 'Master Data > Items > Barcode Misses', 'miss resolved / total miss', 'Review barcode misses, resolve ke item yang benar, rekap resolved dibanding total miss.', 'Sudah ada resolve barcode misses.'],
            ['Admin Sistem', 'Master Data Correction Rate', 'Frekuensi koreksi data master/transaksi oleh admin.', '<=', 0, 'target', 'monthly', 'Otomatis', 'Ada', 'Reports > Activity Logs', 'jumlah update/delete/import correction per periode', 'Filter activity logs untuk modul master/transaksi dan action koreksi.', 'Target awal di Excel masih target bebas. Definisikan action mana yang dianggap correction.'],
            ['Admin Inbound', 'Inbound Input Completion', 'Dokumen inbound yang berhasil dicatat lengkap dan approved/completed.', '>=', 98, '%', 'daily', 'Otomatis', 'Ada', 'Inbound Receipts', 'dokumen inbound completed/approved / total dokumen inbound', 'Input inbound, import/manual bila perlu, lakukan scan sampai complete/approved.', 'Gunakan status dokumen sebagai definisi selesai.'],
            ['Admin Inbound', 'Inbound SKU Qty Accuracy', 'Kesesuaian expected qty dengan scanned qty inbound.', '>=', 99, '%', 'monthly', 'Otomatis', 'Ada', 'Inbound Scan', '1 - (SUM(ABS(scanned_qty - expected_qty)) / SUM(expected_qty))', 'Buka sesi scan inbound, scan SKU/koli, complete, lalu rekap selisih expected vs scanned.', 'Pastikan semua inbound memakai workflow scan.'],
            ['Staff Gudang Inbound', 'Inbound Scan Productivity', 'Qty/SKU inbound yang discan per user.', '>=', 0, 'target shift', 'daily', 'Semi Otomatis', 'Sebagian Ada', 'Mobile/Admin > Inbound Scan', 'SUM(scanned_qty) per user per tanggal/shift', 'Staff login akun personal, scan inbound, sistem mencatat user terakhir/session.', 'Target awal di Excel masih target shift. Saran: tambah inbound_scan_events agar produktivitas per scan dan per user lebih presisi.'],
            ['Admin Outbound', 'Resi Import Timeliness', 'Kecepatan import resi setelah file/order diterima.', '<=', 0, 'SLA', 'daily', 'Otomatis', 'Ada', 'Resi Import', 'AVG(created_at - tanggal_upload/file received time)', 'Import resi, sistem menyimpan tanggal upload/created_at, rekap lead time.', 'Target awal di Excel masih target SLA. Jika waktu file diterima beda dari tanggal_upload, tambahkan file_received_at.'],
            ['Admin Outbound', 'Resi Cancel Control', 'Rasio resi cancel setelah upload/import.', '<=', 0.5, '%', 'daily', 'Otomatis', 'Ada', 'Resi Import', 'resi canceled / total resi periode', 'Cancel/uncancel dari modul Resi Import dengan alasan, lalu rekap status canceled.', 'Saran: buat master kategori cancel_reason.'],
            ['Admin Outbound', 'Delivery Note Completion', 'Dokumen/surat jalan yang tersedia untuk outbound approved.', '>=', 98, '%', 'daily', 'Otomatis', 'Ada', 'Outbound > Delivery Notes', 'delivery note printable / total outbound approved atau batch pengiriman', 'Approve outbound, buka delivery notes, print surat jalan untuk pengiriman.', 'Saran: definisikan batch pengiriman untuk denominator KPI.'],
            ['QC', 'QC Processing Speed', 'Kecepatan QC per resi/paket.', '<=', 30, 'detik/paket', 'daily', 'Otomatis', 'Ada', 'Outbound > QC History', 'AVG(completed_at - started_at)', 'QC scan resi, validasi SKU, complete. QC History mencatat started_at dan completed_at.', 'Gunakan status passed sebagai selesai.'],
            ['QC', 'QC First Pass Rate', 'Resi yang lolos QC tanpa reset/substitution.', '>=', 98, '%', 'daily', 'Otomatis', 'Ada', 'Outbound > QC History', 'passed tanpa reset/substitution / total passed', 'Pantau QC History, gunakan reset_count dan substitution_count untuk menandai gagal first pass.', 'Sudah tersedia di QC History.'],
            ['QC', 'QC Duplicate Scan Rate', 'Percobaan scan ulang pada resi yang sudah diproses QC.', '<=', 0.5, '%', 'daily', 'Otomatis', 'Ada', 'Outbound > QC History > Duplicate Attempts', 'duplicate attempts / total QC scans', 'QC duplicate attempt otomatis tercatat saat user scan resi yang sudah ada/selesai.', 'Fitur pencatatan duplicate attempt sudah ada.'],
            ['QC', 'QC Substitution Rate', 'Rasio penggantian item saat QC.', '<=', 0, 'target', 'daily', 'Otomatis', 'Ada', 'Outbound > QC History', 'jumlah substitution / total QC completed', 'QC melakukan substitute saat item tidak sesuai, sistem menyimpan original/replacement SKU dan reason.', 'Target awal di Excel masih target bebas. Perlu target berbeda untuk item tertentu bila substitution memang wajar.'],
            ['Picker', 'Picking Productivity', 'Qty item yang dipick per picker.', '>=', 0, 'target shift', 'daily', 'Otomatis', 'Ada', 'Inventory > Picking List', 'SUM(qty) per picker user_id/employee', 'Picker menjalankan sesi picking dan item picked tersimpan. Rekap qty per picker.', 'Target awal di Excel masih target shift. Pastikan setiap picker memakai akun personal.'],
            ['Picker', 'Picking Exception Rate', 'Item picking yang menjadi exception.', '<=', 1, '%', 'daily', 'Semi Otomatis', 'Sebagian Ada', 'Picking List Exceptions, QC History', 'exception qty / total picking qty', 'Catat exception pada picking, cocokkan dengan qty picking dan mismatch QC.', 'Saran: pastikan exception terhubung ke picker/session agar KPI per orang akurat.'],
            ['Packer', 'Packing Output', 'Jumlah paket yang dikonfirmasi packed by packer.', '>=', 0, 'target shift', 'daily', 'Otomatis', 'Ada', 'Outbound > Scan Out', 'COUNT(scan_out) per packed_employee_id', 'Pada halaman Scan Out, pilih packer sebelum scan. Sistem menyimpan packed_employee_id dan packed_at saat scan out berhasil.', 'Target awal di Excel masih target shift. Field packed_employee_id dan packed_at sudah tersedia.'],
            ['Packer', 'Packing Confirmation Rate', 'Scan out yang memiliki data packed by.', '>=', 99, '%', 'daily', 'Otomatis', 'Ada', 'Outbound > Scan Out History', 'scan out dengan packed_employee_id / total scan out', 'Operator wajib memilih packer saat scan out; cek history untuk paket tanpa packed by.', 'Ini mengukur disiplin input packer.'],
            ['Packer', 'Packing Damage Return Rate', 'Retur rusak karena packing dibanding total paket packed.', '<=', 0.1, '%', 'monthly', 'Semi Otomatis', 'Sebagian Ada', 'Customer Returns, Scan Out History', 'retur root_cause damaged_packing per packer / total packed packer', 'Admin return isi root cause. Cocokkan retur ke resi/scan out untuk attribution packer.', 'Saran: pastikan retur selalu memiliki relasi resi/scan out untuk attribution otomatis.'],
            ['Scan Outbound', 'Scan Out Productivity', 'Jumlah resi scan out per operator.', '>=', 0, 'target shift', 'daily', 'Otomatis', 'Ada', 'Reports > Scan Out Reports, Scan Out History', 'COUNT(resi_id) per scanned_by per hari', 'Operator scan paket keluar. Scan Out Reports menampilkan total_scan, unique_scan, first/last scan, avg_per_hour.', 'Target awal di Excel masih target shift.'],
            ['Scan Outbound', 'Missing Scan Out Rate', 'Resi aktif import yang belum scan out.', '<=', 2, '%', 'daily', 'Otomatis', 'Ada', 'Reports > Scan Out Reports', 'missing_total / import_total aktif', 'Buka Scan Out Reports, sistem membandingkan resi aktif dan shipment_scan_outs.', 'Sudah ada comparison data pada report.'],
            ['Scan Outbound', 'Courier Sorting Accuracy', 'Kesesuaian kurir scan out dengan kurir resi.', '>=', 99.5, '%', 'daily', 'Semi Otomatis', 'Sebagian Ada', 'Scan Out History, Resi Import', 'scan out dengan kurir sesuai / total scan out', 'Bandingkan kurir pada resi dengan kurir tersimpan di scan out.', 'Saran: buat report khusus mismatch kurir di UI.'],
            ['Admin Return', 'Return Input Lead Time', 'Kecepatan input retur dari tanggal diterima.', '<=', 24, 'jam', 'daily', 'Otomatis', 'Ada', 'Inventory > Customer Returns, Reports > Returns', 'created_at - received_at', 'Input retur customer dengan received_at, lalu export/report retur untuk menghitung lead time input.', 'Sudah tersedia.'],
            ['Admin Return', 'Root Cause Completion Rate', 'Item retur yang memiliki root cause.', '>=', 100, '%', 'daily', 'Otomatis', 'Ada', 'Customer Returns, Reports > Returns', 'item retur dengan root_cause terisi / total item retur', 'Pada form retur, isi root cause per item. Export customer returns/return report untuk cek kelengkapan.', 'Root cause sudah ada pada customer return item.'],
            ['Admin Return', 'Return Finalization Lead Time', 'Kecepatan finalisasi retur ke stok baik/rusak/hilang.', '<=', 0, 'SLA', 'daily', 'Otomatis', 'Ada', 'Customer Returns, Damaged Goods', 'finalized_at - received_at', 'Terima retur, inspeksi item, isi qty bagus/rusak, finalisasi retur. Sistem membuat barang rusak bila ada damaged qty.', 'Target awal di Excel masih target SLA. Gunakan status completed/no_received.'],
            ['Staff Gudang Return', 'Damaged Intake Completion', 'Barang rusak retur masuk ke modul barang rusak.', '>=', 100, '%', 'daily', 'Otomatis', 'Ada', 'Customer Returns, Damaged Goods', 'damaged qty yang punya damaged_good_id / total damaged qty retur', 'Finalisasi retur dengan damaged_qty. Cek Damaged Goods/export untuk memastikan barang rusak tercatat.', 'Export Barang Rusak sudah tersedia.'],
            ['Staff Gudang Return', 'Damaged Goods Aging Control', 'Barang rusak tidak terlalu lama mengendap sebelum dialokasi/rework.', '<=', 0, 'hari', 'weekly', 'Otomatis', 'Ada', 'Inventory > Damaged Goods, Damaged Allocations', 'qty remaining per aging bucket', 'Approve intake barang rusak, pantau aging cards/saldo rusak, lakukan damaged allocation/rework untuk mengurangi sisa.', 'Target awal di Excel masih target aging. Sudah ada aging summary dan export barang rusak.'],
            ['Staff Gudang Return', 'Return Sorting Productivity', 'Qty item retur yang disortir per inspector.', '>=', 0, 'target shift', 'daily', 'Semi Otomatis', 'Sebagian Ada', 'Customer Returns', 'SUM(received_qty) per inspected_by', 'Gunakan inspected_by sebagai PIC sortir, rekap qty item yang diterima/disortir.', 'Target awal di Excel masih target shift. Saran: jika sorter beda dari inspector, tambah sorted_by/sorted_at.'],
            ['HR / Admin Absensi', 'Attendance Data Completeness', 'Karyawan aktif yang memiliki jadwal dan data absensi.', '>=', 99, '%', 'monthly', 'Otomatis', 'Ada', 'Attendance > Employees, Schedules, Attendances, Reports', 'karyawan aktif dengan jadwal dan attendance / total karyawan aktif', 'Kelola employee, shift, schedule/import schedule, sinkronkan device/raw logs, lalu cek attendance report.', 'Sudah ada employee, schedule, raw logs, attendance export/report.'],
            ['HR / Admin Absensi', 'Leave Approval SLA', 'Kecepatan approve/reject pengajuan izin/cuti.', '<=', 0, 'SLA', 'monthly', 'Otomatis', 'Ada', 'Attendance > Leaves', 'approved_at - created_at', 'Karyawan/admin input leave, HR approve/reject, sistem menyimpan status dan approved_at.', 'Target awal di Excel masih target SLA. Pastikan created_at dipakai sebagai waktu pengajuan.'],
        ];

        $now = now();

        foreach ($rows as $row) {
            [$role, $metric, $definition, $operator, $target, $unit, $period, $trackingType, $featureStatus, $module, $formula, $flow, $note] = $row;

            $exists = DB::table('kpi_definitions')
                ->where('role_name', $role)
                ->where('metric_name', $metric)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('kpi_definitions')->insert([
                'role_name' => $role,
                'metric_name' => $metric,
                'description' => $this->buildDescription($definition, $trackingType, $featureStatus, $module, $formula, $flow, $note),
                'target_operator' => $operator,
                'target_value' => $target,
                'unit' => $unit,
                'weight' => 100,
                'period_type' => $period,
                'source_type' => str_contains(strtolower($trackingType), 'otomatis') ? 'auto' : 'manual',
                'formula_key' => Str::slug($metric, '_'),
                'is_active' => true,
                'created_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function buildDescription(string $definition, string $trackingType, string $featureStatus, string $module, string $formula, string $flow, string $note): string
    {
        return implode("\n", [
            $definition,
            'Tipe tracking: '.$trackingType,
            'Status fitur: '.$featureStatus,
            'Modul aplikasi: '.$module,
            'Formula/cara hitung: '.$formula,
            'Alur tracking: '.$flow,
            'Catatan: '.$note,
        ]);
    }
}
