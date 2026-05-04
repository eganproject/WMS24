<?php

namespace App\Imports;

use App\Models\Item;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Support\OutboundKoliExpectation;
use App\Support\WarehouseService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class OutboundReturnsImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    /** @var array<string,array{ref_no:?string,supplier_id:?int,surat_jalan_no:?string,surat_jalan_at:?string,note:?string,transacted_at:?string,warehouse_id:?int,items:array<int,array{item_id:int,qty:int,note:?string}>}> */
    public array $groups = [];

    public function __construct(private readonly bool $requireSupplier = false)
    {
    }

    public function collection(Collection $rows)
    {
        if ($rows->isEmpty()) {
            throw ValidationException::withMessages([
                'file' => 'File kosong',
            ]);
        }

        $first = $rows->first();
        $headers = array_keys($first?->toArray() ?? []);
        if (!in_array('sku', $headers, true)) {
            throw ValidationException::withMessages([
                'file' => 'Header wajib: sku, qty/koli (opsional: ref_no, surat_jalan_no, surat_jalan_at, note, item_note, transacted_at, warehouse/gudang)',
            ]);
        }
        $qtyKey = $this->detectQtyKey($headers);
        $koliKey = $this->detectKoliKey($headers);
        if ($qtyKey === null && $koliKey === null) {
            throw ValidationException::withMessages([
                'file' => 'Header qty/koli wajib (gunakan: qty/quantity/jumlah/stok/stock atau koli/kolian/isi_koli)',
            ]);
        }
        if ($this->requireSupplier && $this->detectSupplierKey($headers) === null) {
            throw ValidationException::withMessages([
                'file' => 'Header supplier wajib untuk import retur outbound (gunakan: supplier/supplier_name/nama_supplier)',
            ]);
        }

        $warehouseMaps = $this->buildWarehouseMaps();
        $supplierMaps = $this->requireSupplier ? $this->buildSupplierMaps() : ['ids' => [], 'names' => []];

        $skus = $rows->map(fn ($row) => trim((string) ($row['sku'] ?? '')))
            ->filter()
            ->unique()
            ->values();

        $items = Item::whereIn('sku', $skus)->get(['id', 'sku', 'koli_qty'])->keyBy('sku');
        $skuMap = $items->pluck('id', 'sku')->all();

        $missing = [];
        $errors = [];
        $rowIndex = 1;
        foreach ($rows as $row) {
            $rowIndex++;
            $sku = trim((string) ($row['sku'] ?? ''));
            if ($sku === '') {
                continue;
            }
            if (!isset($skuMap[$sku])) {
                $missing[$sku] = true;
                continue;
            }
            $item = $items->get($sku);
            $qty = $qtyKey ? $this->parseQty($row, $qtyKey) : 0;
            $koli = $koliKey ? $this->parseQty($row, $koliKey) : 0;
            $warehouseId = $this->parseWarehouseId($row, $warehouseMaps, $errors, $rowIndex);

            if (!$item) {
                $missing[$sku] = true;
                continue;
            }

            $resolved = [
                'qty' => $qty,
            ];

            $usesKoli = (int) $warehouseId === WarehouseService::defaultWarehouseId();
            if ($usesKoli) {
                if ($koli <= 0) {
                    $errors[] = "Baris {$rowIndex}: koli wajib diisi untuk outbound dari Gudang Besar";
                    continue;
                }

                try {
                    $resolved = OutboundKoliExpectation::resolve($item, $qty, $koli);
                } catch (ValidationException $e) {
                    $errors[] = "Baris {$rowIndex}: ".(collect($e->errors())->flatten()->first() ?? $e->getMessage());
                    continue;
                }
            } elseif ($qty <= 0) {
                $errors[] = "Baris {$rowIndex}: qty tidak valid untuk SKU {$sku}";
                continue;
            }

            $ref = trim((string) ($row['ref_no'] ?? ''));
            $supplierId = $this->requireSupplier
                ? $this->parseSupplierId($row, $supplierMaps, $errors, $rowIndex)
                : null;
            $note = trim((string) ($row['note'] ?? ''));
            $itemNote = trim((string) ($row['item_note'] ?? $row['note_item'] ?? ''));
            $transactedAt = trim((string) ($row['transacted_at'] ?? $row['tanggal'] ?? ''));
            $suratJalanNo = trim((string) ($row['surat_jalan_no'] ?? $row['sj_no'] ?? ''));
            $suratJalanAt = trim((string) ($row['surat_jalan_at'] ?? $row['tanggal_surat_jalan'] ?? ''));

            $groupKey = implode('::', [
                $ref !== '' ? $ref : '__default__',
                $suratJalanNo !== '' ? $suratJalanNo : 'null_sj',
                $this->requireSupplier && $supplierId ? (string) $supplierId : 'null_supplier',
                (string) ($warehouseId ?: 'null'),
            ]);
            if (!isset($this->groups[$groupKey])) {
                $this->groups[$groupKey] = [
                    'ref_no' => $ref !== '' ? $ref : null,
                    'supplier_id' => $supplierId,
                    'surat_jalan_no' => $suratJalanNo !== '' ? $suratJalanNo : null,
                    'surat_jalan_at' => $suratJalanAt !== '' ? $suratJalanAt : null,
                    'note' => $note !== '' ? $note : null,
                    'transacted_at' => $transactedAt !== '' ? $transactedAt : null,
                    'warehouse_id' => $warehouseId,
                    'items' => [],
                ];
            } else {
                if ($this->groups[$groupKey]['supplier_id'] === null && $supplierId) {
                    $this->groups[$groupKey]['supplier_id'] = $supplierId;
                }
                if ($this->groups[$groupKey]['surat_jalan_no'] === null && $suratJalanNo !== '') {
                    $this->groups[$groupKey]['surat_jalan_no'] = $suratJalanNo;
                }
                if ($this->groups[$groupKey]['surat_jalan_at'] === null && $suratJalanAt !== '') {
                    $this->groups[$groupKey]['surat_jalan_at'] = $suratJalanAt;
                }
                if ($this->groups[$groupKey]['note'] === null && $note !== '') {
                    $this->groups[$groupKey]['note'] = $note;
                }
                if ($this->groups[$groupKey]['transacted_at'] === null && $transactedAt !== '') {
                    $this->groups[$groupKey]['transacted_at'] = $transactedAt;
                }
                if ($this->groups[$groupKey]['warehouse_id'] === null && $warehouseId) {
                    $this->groups[$groupKey]['warehouse_id'] = $warehouseId;
                }
            }

            $itemId = (int) $skuMap[$sku];
            if (!isset($this->groups[$groupKey]['items'][$itemId])) {
                $this->groups[$groupKey]['items'][$itemId] = [
                    'item_id' => $itemId,
                    'qty' => $resolved['qty'],
                    'note' => $itemNote !== '' ? $itemNote : null,
                ];
            } else {
                $this->groups[$groupKey]['items'][$itemId]['qty'] += $resolved['qty'];
                if ($itemNote !== '' && empty($this->groups[$groupKey]['items'][$itemId]['note'])) {
                    $this->groups[$groupKey]['items'][$itemId]['note'] = $itemNote;
                }
            }
        }

        if (!empty($missing)) {
            $list = implode(', ', array_keys($missing));
            throw ValidationException::withMessages([
                'file' => 'SKU tidak ditemukan: '.$list,
            ]);
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages([
                'file' => implode(' | ', array_slice($errors, 0, 5)),
            ]);
        }

        foreach ($this->groups as $key => $group) {
            $items = array_values($group['items'] ?? []);
            if (empty($items)) {
                unset($this->groups[$key]);
                continue;
            }
            $this->groups[$key]['items'] = $items;
        }

        if (empty($this->groups)) {
            throw ValidationException::withMessages([
                'file' => 'Tidak ada data valid untuk diimport',
            ]);
        }
    }

    private function detectQtyKey(array $headers): ?string
    {
        foreach (['qty', 'quantity', 'jumlah', 'stok', 'stock'] as $key) {
            if (in_array($key, $headers, true)) {
                return $key;
            }
        }
        return null;
    }

    private function detectKoliKey(array $headers): ?string
    {
        foreach (['koli', 'kolian', 'isi_koli', 'koli_qty', 'qty_koli'] as $key) {
            if (in_array($key, $headers, true)) {
                return $key;
            }
        }

        return null;
    }

    private function detectSupplierKey(array $headers): ?string
    {
        foreach (['supplier', 'supplier_name', 'nama_supplier'] as $key) {
            if (in_array($key, $headers, true)) {
                return $key;
            }
        }
        return null;
    }

    private function parseQty($row, string $key): int
    {
        $raw = null;
        if (is_array($row) && array_key_exists($key, $row)) {
            $raw = $row[$key];
        } elseif ($row instanceof Collection && $row->has($key)) {
            $raw = $row->get($key);
        } elseif (isset($row[$key])) {
            $raw = $row[$key];
        }
        if ($raw === null || $raw === '') {
            return 0;
        }
        $value = is_numeric($raw) ? (int) $raw : (int) preg_replace('/[^0-9\-]/', '', (string) $raw);
        return $value > 0 ? $value : 0;
    }

    /**
     * @param array{codes:array<string,int>,names:array<string,int>,ids:array<int,bool>} $maps
     * @param array<int,string> $errors
     */
    private function parseWarehouseId($row, array $maps, array &$errors, int $rowIndex): ?int
    {
        $raw = null;
        foreach (['warehouse', 'gudang', 'warehouse_code', 'gudang_code', 'warehouse_name', 'gudang_name', 'nama_gudang', 'kode_gudang'] as $key) {
            if (is_array($row) && array_key_exists($key, $row)) {
                $raw = $row[$key];
                break;
            }
            if ($row instanceof Collection && $row->has($key)) {
                $raw = $row->get($key);
                break;
            }
            if (isset($row[$key])) {
                $raw = $row[$key];
                break;
            }
        }

        if ($raw === null || $raw === '') {
            return null;
        }

        $value = trim((string) $raw);
        if ($value === '') {
            return null;
        }

        if (ctype_digit($value)) {
            $id = (int) $value;
            if ($id > 0 && isset($maps['ids'][$id])) {
                return $id;
            }
        }

        $codeKey = strtoupper($value);
        if (isset($maps['codes'][$codeKey])) {
            return $maps['codes'][$codeKey];
        }

        $nameKey = $this->normalizeWarehouseName($value);
        if (isset($maps['names'][$nameKey])) {
            return $maps['names'][$nameKey];
        }

        $errors[] = "Baris {$rowIndex}: gudang tidak ditemukan ({$value})";
        return null;
    }

    /**
     * @return array{codes:array<string,int>,names:array<string,int>,ids:array<int,bool>}
     */
    private function buildWarehouseMaps(): array
    {
        $codes = [];
        $names = [];
        $ids = [];
        $warehouses = Warehouse::query()->get(['id', 'code', 'name']);
        foreach ($warehouses as $warehouse) {
            $id = (int) $warehouse->id;
            $ids[$id] = true;
            $code = strtoupper((string) $warehouse->code);
            if ($code !== '') {
                $codes[$code] = $id;
            }
            $name = $this->normalizeWarehouseName((string) $warehouse->name);
            if ($name !== '') {
                $names[$name] = $id;
            }
        }
        return [
            'codes' => $codes,
            'names' => $names,
            'ids' => $ids,
        ];
    }

    /**
     * @return array{ids:array<int,bool>,names:array<string,int>}
     */
    private function buildSupplierMaps(): array
    {
        $ids = [];
        $names = [];
        $suppliers = Supplier::query()->get(['id', 'name']);

        foreach ($suppliers as $supplier) {
            $id = (int) $supplier->id;
            $ids[$id] = true;
            $name = $this->normalizeSupplierName((string) $supplier->name);
            if ($name !== '') {
                $names[$name] = $id;
            }
        }

        return [
            'ids' => $ids,
            'names' => $names,
        ];
    }

    /**
     * @param array{ids:array<int,bool>,names:array<string,int>} $maps
     * @param array<int,string> $errors
     */
    private function parseSupplierId($row, array $maps, array &$errors, int $rowIndex): ?int
    {
        $raw = null;
        foreach (['supplier', 'supplier_name', 'nama_supplier'] as $key) {
            if (is_array($row) && array_key_exists($key, $row)) {
                $raw = $row[$key];
                break;
            }
            if ($row instanceof Collection && $row->has($key)) {
                $raw = $row->get($key);
                break;
            }
            if (isset($row[$key])) {
                $raw = $row[$key];
                break;
            }
        }

        if ($raw === null || trim((string) $raw) === '') {
            if ($this->requireSupplier) {
                $errors[] = "Baris {$rowIndex}: supplier wajib diisi";
            }
            return null;
        }

        $value = trim((string) $raw);
        if (ctype_digit($value)) {
            $id = (int) $value;
            if ($id > 0 && isset($maps['ids'][$id])) {
                return $id;
            }
        }

        $normalizedName = $this->normalizeSupplierName($value);
        if (isset($maps['names'][$normalizedName])) {
            return $maps['names'][$normalizedName];
        }

        $errors[] = "Baris {$rowIndex}: supplier tidak ditemukan ({$value})";
        return null;
    }

    private function normalizeWarehouseName(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/\\s+/', ' ', $value) ?? $value;
        return $value;
    }

    private function normalizeSupplierName(string $value): string
    {
        $value = strtolower(trim($value));
        return preg_replace('/\\s+/', ' ', $value) ?? $value;
    }
}
