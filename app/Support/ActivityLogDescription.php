<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ActivityLogDescription
{
    private const MAX_ACTION_LENGTH = 190;

    public static function describe(Request $request, Response $response, array $payload): string
    {
        $context = self::context($request, $response, $payload);
        $target = $context['target'] ? ' '.$context['target'] : '';
        $result = $context['result'];

        return Str::limit(
            "{$result} {$context['verb']} {$context['module']}{$target}",
            self::MAX_ACTION_LENGTH,
            ''
        );
    }

    public static function payload(Request $request, Response $response, array $payload): array
    {
        $context = self::context($request, $response, $payload);

        return [
            'ringkasan' => [
                'hasil' => $context['result'],
                'aktivitas' => self::describe($request, $response, $payload),
                'modul' => $context['module'],
                'aksi' => $context['verb'],
                'target' => $context['target'] ?: '-',
                'status_http' => $response->getStatusCode(),
                'data_utama' => self::summarizePayload($payload),
            ],
            'data_dikirim' => $payload,
        ];
    }

    private static function context(Request $request, Response $response, array $payload): array
    {
        $routeName = (string) ($request->route()?->getName() ?? '');
        $method = strtoupper($request->method());

        return [
            'result' => self::resultLabel($response),
            'verb' => self::verbLabel($routeName, $method),
            'module' => self::moduleLabel($routeName),
            'target' => self::targetLabel($request, $payload),
        ];
    }

    private static function resultLabel(Response $response): string
    {
        return $response->getStatusCode() >= 400 ? 'Gagal' : 'Berhasil';
    }

    private static function verbLabel(string $routeName, string $method): string
    {
        $suffix = self::routeSuffix($routeName);

        $map = [
            'store' => 'menambahkan',
            'update' => 'mengubah',
            'destroy' => 'menghapus',
            'delete' => 'menghapus',
            'import' => 'mengimpor',
            'items-import' => 'mengimpor item',
            'approve' => 'menyetujui',
            'cancel' => 'membatalkan',
            'uncancel' => 'mengaktifkan kembali',
            'finalize' => 'memfinalisasi',
            'update-safety' => 'mengubah safety stock',
            'recalculate' => 'menghitung ulang',
            'store-qty' => 'menambahkan qty',
            'exception-return' => 'mengembalikan exception',
            'qc' => 'melakukan QC',
            'open' => 'membuka sesi',
            'scan' => 'melakukan scan',
            'scan-sku' => 'melakukan scan SKU',
            'complete' => 'menyelesaikan',
            'reset' => 'mereset',
            'hold' => 'menahan',
            'batch.create' => 'membuat batch',
            'batch.complete' => 'menyelesaikan batch',
            'items.store' => 'menambahkan item',
            'items.update' => 'mengubah item',
            'items.destroy' => 'menghapus item',
            'profile.update' => 'mengubah',
            'profile.destroy' => 'menghapus akun',
        ];

        if (isset($map[$suffix])) {
            return $map[$suffix];
        }

        return match ($method) {
            'POST' => 'menyimpan',
            'PUT', 'PATCH' => 'mengubah',
            'DELETE' => 'menghapus',
            default => 'melakukan aktivitas pada',
        };
    }

    private static function moduleLabel(string $routeName): string
    {
        $map = [
            'profile' => 'Profil Pengguna',
            'admin.masterdata.users' => 'User',
            'admin.masterdata.roles' => 'Role',
            'admin.masterdata.areas' => 'Area Gudang',
            'admin.masterdata.locations' => 'Lokasi/Rak Gudang',
            'admin.masterdata.kurir' => 'Kurir',
            'admin.masterdata.suppliers' => 'Supplier',
            'admin.masterdata.menus' => 'Menu Aplikasi',
            'admin.masterdata.categories' => 'Kategori Item',
            'admin.masterdata.items' => 'Master Item',
            'admin.masterdata.stores' => 'Toko',
            'admin.masterdata.permissions' => 'Hak Akses',
            'admin.inventory.item-stocks' => 'Stok Item',
            'admin.inventory.stock-transfers' => 'Transfer Stok',
            'admin.inventory.stock-opname' => 'Stock Opname',
            'admin.inventory.stock-adjustments' => 'Penyesuaian Stok',
            'admin.inventory.damaged-goods' => 'Barang Rusak',
            'admin.inventory.damaged-allocations' => 'Alokasi Barang Rusak',
            'admin.inventory.rework-recipes' => 'Resep Rework',
            'admin.inventory.resi-import' => 'Import Resi',
            'admin.inventory.customer-returns' => 'Retur Customer',
            'admin.inventory.picking-list' => 'Picking List',
            'admin.inbound.scan' => 'Scan Inbound',
            'admin.inbound.receipts' => 'Inbound Receipt',
            'admin.inbound.returns' => 'Retur Inbound',
            'admin.inbound.manuals' => 'Inbound Manual',
            'admin.outbound.pickers' => 'Outbound Picker',
            'admin.outbound.manuals' => 'Outbound Manual',
            'admin.outbound.returns' => 'Retur Outbound',
            'admin.outbound.manual-qc' => 'QC Manual Outbound',
            'admin.outbound.qc-scan' => 'QC Outbound',
            'admin.outbound.qc-scan-exceptions' => 'Exception QC Outbound',
            'mobile.inbound-scan' => 'Scan Inbound Mobile',
            'mobile.qc' => 'QC Mobile',
            'mobile.scan-out' => 'Scan Out Mobile',
            'opname.batch' => 'Batch Stock Opname Mobile',
            'opname.items' => 'Item Stock Opname Mobile',
        ];

        foreach ($map as $prefix => $label) {
            if ($routeName === $prefix || str_starts_with($routeName, $prefix.'.')) {
                return $label;
            }
        }

        return self::humanize((string) Str::of($routeName)->replace('.', ' '));
    }

    private static function routeSuffix(string $routeName): string
    {
        $specialSuffixes = [
            'batch.create',
            'batch.complete',
            'items.store',
            'items.update',
            'items.destroy',
            'profile.update',
            'profile.destroy',
        ];

        foreach ($specialSuffixes as $suffix) {
            if (str_ends_with($routeName, '.'.$suffix) || $routeName === $suffix) {
                return $suffix;
            }
        }

        return (string) Str::of($routeName)->afterLast('.');
    }

    private static function targetLabel(Request $request, array $payload): string
    {
        $routeParameters = $request->route()?->parameters() ?? [];
        foreach ($routeParameters as $value) {
            $label = self::valueLabel($value);
            if ($label !== '') {
                return $label;
            }
        }

        foreach (['code', 'id', 'ids', 'source_code', 'no_resi', 'resi_no', 'sku'] as $key) {
            if (!array_key_exists($key, $payload)) {
                continue;
            }

            $label = self::valueLabel($payload[$key]);
            if ($label !== '') {
                return $label;
            }
        }

        return '';
    }

    private static function valueLabel(mixed $value): string
    {
        if ($value instanceof Model) {
            foreach (['code', 'sku', 'name', 'no_resi', 'resi_no', 'id'] as $attribute) {
                $attributeValue = $value->getAttribute($attribute);
                if ($attributeValue !== null && $attributeValue !== '') {
                    return '#'.$attributeValue;
                }
            }
        }

        if (is_array($value)) {
            $values = array_values(array_filter($value, fn ($item) => $item !== null && $item !== ''));
            if (empty($values)) {
                return '';
            }

            return count($values) === 1 ? '#'.$values[0] : count($values).' data';
        }

        if (is_scalar($value) && (string) $value !== '') {
            return '#'.$value;
        }

        return '';
    }

    private static function summarizePayload(array $payload): array
    {
        $summary = [];
        foreach ($payload as $key => $value) {
            if (str_starts_with((string) $key, '_')) {
                continue;
            }
            if (count($summary) >= 12) {
                $summary['Lainnya'] = 'Masih ada data tambahan pada payload detail.';
                break;
            }

            $summary[self::fieldLabel((string) $key)] = self::summaryValue($value);
        }

        return $summary;
    }

    private static function summaryValue(mixed $value): mixed
    {
        if (is_array($value)) {
            if (array_key_exists('name', $value) && array_key_exists('size', $value)) {
                return sprintf('%s (%s bytes)', $value['name'], $value['size']);
            }

            return count($value).' baris/data';
        }

        if (is_bool($value)) {
            return $value ? 'Ya' : 'Tidak';
        }

        if ($value === null || $value === '') {
            return '-';
        }

        return is_scalar($value)
            ? Str::limit((string) $value, 120)
            : gettype($value);
    }

    private static function fieldLabel(string $field): string
    {
        $map = [
            '_method' => 'Metode Form',
            'id' => 'ID',
            'ids' => 'Jumlah Data Dipilih',
            'name' => 'Nama',
            'email' => 'Email',
            'username' => 'Username',
            'role' => 'Role',
            'role_id' => 'Role',
            'sku' => 'SKU',
            'item_id' => 'Item',
            'items' => 'Daftar Item',
            'qty' => 'Qty',
            'stock' => 'Stok',
            'safety_stock' => 'Safety Stock',
            'warehouse_id' => 'Gudang',
            'source_warehouse_id' => 'Gudang Asal',
            'target_warehouse_id' => 'Gudang Tujuan',
            'supplier_id' => 'Supplier',
            'type' => 'Tipe',
            'status' => 'Status',
            'code' => 'Kode',
            'source_code' => 'Kode Referensi',
            'source_ref' => 'Referensi',
            'note' => 'Catatan',
            'description' => 'Deskripsi',
            'transacted_at' => 'Tanggal Transaksi',
            'received_at' => 'Tanggal Diterima',
            'date' => 'Tanggal',
            'reason_code' => 'Alasan',
            'source_items' => 'Item Sumber',
            'output_items' => 'Item Hasil',
            'recipe_id' => 'Resep',
            'recipe_multiplier' => 'Batch Resep',
            'resi_no' => 'No. Resi',
            'no_resi' => 'No. Resi',
            'order_ref' => 'Referensi Order',
            'scan_code' => 'Kode Scan',
        ];

        return $map[$field] ?? self::humanize($field);
    }

    private static function humanize(string $value): string
    {
        $value = trim(str_replace(['_', '-', '.'], ' ', $value));
        if ($value === '') {
            return 'Aktivitas';
        }

        return Str::title($value);
    }
}
