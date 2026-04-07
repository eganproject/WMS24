<?php

namespace App\Imports;

use App\Models\Category;
use App\Models\Item;
use App\Models\ItemStock;
use App\Support\LocationService;
use App\Support\WarehouseService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ItemsImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    public int $created = 0;
    public int $updated = 0;
    /** @var array<int,int> */
    public array $initialStocks = [];
    /** @var array<int,array<int,int>> */
    public array $initialStocksByWarehouse = [];

    private ?int $defaultCategoryId = null;

    public function collection(Collection $rows)
    {
        if ($rows->isEmpty()) {
            throw ValidationException::withMessages([
                'file' => 'File kosong',
            ]);
        }

        $first = $rows->first();
        $headers = array_keys($first?->toArray() ?? []);
        $required = ['sku', 'name'];
        $missing = array_diff($required, $headers);
        if ($missing) {
            $detected = implode(', ', array_filter($headers, fn ($h) => $h !== null && $h !== ''));
            $detected = $detected !== '' ? $detected : '-';
            throw ValidationException::withMessages([
                'file' => 'Header wajib: sku, name. Header terdeteksi: '.$detected.'. Pastikan header ada di baris pertama.',
            ]);
        }

        foreach ($rows as $row) {
            $sku = trim((string) ($row['sku'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));
            $parentCategoryName = trim((string) ($row['parent_category'] ?? ''));
            $categoryName = trim((string) ($row['category'] ?? ''));
            $description = trim((string) ($row['description'] ?? ''));
            $address = '';
            $hasAddressHeader = $this->hasAnyKey($row, ['address']);
            $hasLocationHeaders = $this->hasAnyKey($row, [
                'lane', 'lane_code', 'ruang', 'ruangan', 'room',
                'rack', 'rak',
                'column', 'col', 'kolom',
                'row', 'baris',
            ]);
            if ($hasAddressHeader) {
                $address = trim((string) ($row['address'] ?? ''));
            } elseif ($hasLocationHeaders) {
                $address = $this->composeAddress($row);
            }
            $stockByWarehouse = $this->parseStockByWarehouse($row);
            $safetyStock = $this->parseSafetyStock($row);
            $koliQty = $this->parseKoliQty($row);

            if ($sku === '' || $name === '') {
                continue;
            }

            $parentCategoryId = 0;
            if ($parentCategoryName !== '') {
                $parentCategory = $this->findOrCreateCategory($parentCategoryName, 0);
                $parentCategoryId = $parentCategory?->id ?? 0;
            }

            $catId = $this->getDefaultCategoryId();
            if ($categoryName !== '') {
                $category = $this->findOrCreateCategory($categoryName, $parentCategoryId);
                $catId = $category?->id ?? $catId;
            }

            $payload = [
                'name' => $name,
                'category_id' => $catId,
                'description' => $description,
            ];
            if ($hasAddressHeader || $hasLocationHeaders) {
                $location = LocationService::resolveLocation($address);
                if ($location) {
                    $payload['location_id'] = $location->id;
                    $payload['address'] = $location->code;
                } else {
                    $payload['location_id'] = null;
                    $payload['address'] = $address;
                }
            }
            if ($safetyStock !== null) {
                $payload['safety_stock'] = $safetyStock;
            }
            if ($koliQty !== null) {
                $payload['koli_qty'] = $koliQty;
            }

            $item = Item::updateOrCreate(
                ['sku' => $sku],
                $payload
            );
            $warehouseId = WarehouseService::defaultWarehouseId();
            ItemStock::firstOrCreate(
                ['item_id' => $item->id, 'warehouse_id' => $warehouseId],
                ['stock' => 0]
            );
            $item->wasRecentlyCreated ? $this->created++ : $this->updated++;

            if (!empty($stockByWarehouse)) {
                foreach ($stockByWarehouse as $warehouseId => $qty) {
                    if ($qty > 0) {
                        $this->initialStocksByWarehouse[$warehouseId][$item->id] = ($this->initialStocksByWarehouse[$warehouseId][$item->id] ?? 0) + $qty;
                    }
                }
                $defaultId = WarehouseService::defaultWarehouseId();
                $defaultQty = (int) ($stockByWarehouse[$defaultId] ?? 0);
                if ($defaultQty > 0) {
                    $this->initialStocks[$item->id] = ($this->initialStocks[$item->id] ?? 0) + $defaultQty;
                }
            }
        }
    }

    protected function parseStockByWarehouse($row): array
    {
        $result = [];
        $defaultId = WarehouseService::defaultWarehouseId();
        $displayId = WarehouseService::displayWarehouseId();

        $default = $this->parseIntByKeys($row, [
            'stock_gudang_besar',
            'stok_gudang_besar',
            'stock_besar',
            'stok_besar',
            'stock_main',
            'stok_main',
            'stock_default',
            'stok_default',
        ], $hasDefaultKey);

        $display = $this->parseIntByKeys($row, [
            'stock_gudang_display',
            'stok_gudang_display',
            'stock_display',
            'stok_display',
            'stock_kecil',
            'stok_kecil',
            'stock_showroom',
            'stok_showroom',
        ], $hasDisplayKey);

        $legacy = $this->parseIntByKeys($row, ['stock', 'stok', 'qty'], $hasLegacyKey);

        if ($hasDefaultKey) {
            $result[$defaultId] = $default ?? 0;
        }
        if ($hasDisplayKey) {
            $result[$displayId] = $display ?? 0;
        }
        if (!$hasDefaultKey && !$hasDisplayKey && $hasLegacyKey) {
            $result[$defaultId] = $legacy ?? 0;
        }

        return $result;
    }

    protected function parseSafetyStock($row): ?int
    {
        $raw = null;
        $hasKey = false;
        foreach (['safety_stock', 'stok_pengaman', 'stock_pengaman', 'min_stock', 'minimum_stock'] as $key) {
            if (is_array($row) && array_key_exists($key, $row)) {
                $raw = $row[$key];
                $hasKey = true;
                break;
            }
            if ($row instanceof Collection && $row->has($key)) {
                $raw = $row->get($key);
                $hasKey = true;
                break;
            }
            if (isset($row[$key])) {
                $raw = $row[$key];
                $hasKey = true;
                break;
            }
        }
        if (!$hasKey) {
            return null;
        }
        if ($raw === null || $raw === '') {
            return 0;
        }
        $value = is_numeric($raw) ? (int) $raw : (int) preg_replace('/[^0-9\-]/', '', (string) $raw);
        return $value > 0 ? $value : 0;
    }

    private function parseIntByKeys($row, array $keys, ?bool &$hasKey = null): ?int
    {
        $hasKey = false;
        $raw = null;
        foreach ($keys as $key) {
            if (is_array($row) && array_key_exists($key, $row)) {
                $raw = $row[$key];
                $hasKey = true;
                break;
            }
            if ($row instanceof Collection && $row->has($key)) {
                $raw = $row->get($key);
                $hasKey = true;
                break;
            }
            if (isset($row[$key])) {
                $raw = $row[$key];
                $hasKey = true;
                break;
            }
        }
        if (!$hasKey) {
            return null;
        }
        if ($raw === null || $raw === '') {
            return 0;
        }
        $value = is_numeric($raw) ? (int) $raw : (int) preg_replace('/[^0-9\-]/', '', (string) $raw);
        return $value > 0 ? $value : 0;
    }

    protected function parseKoliQty($row): ?int
    {
        $raw = null;
        $hasKey = false;
        foreach (['koli_qty', 'isi_koli', 'qty_koli', 'kolian', 'koli'] as $key) {
            if (is_array($row) && array_key_exists($key, $row)) {
                $raw = $row[$key];
                $hasKey = true;
                break;
            }
            if ($row instanceof Collection && $row->has($key)) {
                $raw = $row->get($key);
                $hasKey = true;
                break;
            }
            if (isset($row[$key])) {
                $raw = $row[$key];
                $hasKey = true;
                break;
            }
        }
        if (!$hasKey) {
            return null;
        }
        if ($raw === null || $raw === '') {
            return 0;
        }
        $value = is_numeric($raw) ? (int) $raw : (int) preg_replace('/[^0-9\-]/', '', (string) $raw);
        return $value > 0 ? $value : 0;
    }

    protected function findOrCreateCategory(string $name, int $parentId = 0): ?Category
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            return null;
        }
        $normalized = mb_strtolower($trimmed);
        $category = Category::whereRaw('LOWER(name) = ?', [$normalized])->first();
        if ($category) {
            if ($parentId !== 0 && $category->parent_id !== $parentId) {
                $category->parent_id = $parentId;
                $category->save();
            }
            return $category;
        }
        return Category::create([
            'name' => $trimmed,
            'parent_id' => $parentId,
        ]);
    }

    protected function getDefaultCategoryId(): int
    {
        if ($this->defaultCategoryId !== null) {
            return $this->defaultCategoryId;
        }
        $default = Category::firstOrCreate(
            ['name' => 'Tanpa Kategori'],
            ['parent_id' => 0]
        );
        $this->defaultCategoryId = $default->id;
        return $this->defaultCategoryId;
    }

    private function composeAddress($row): string
    {
        $lane = $this->getValue($row, ['lane', 'lane_code', 'ruang', 'ruangan', 'room']);
        $rack = $this->getValue($row, ['rack', 'rak']);
        $col = $this->getValue($row, ['column', 'col', 'kolom']);
        $rowNo = $this->getValue($row, ['row', 'baris']);

        $lane = trim((string) ($lane ?? ''));
        $rack = trim((string) ($rack ?? ''));
        $col = trim((string) ($col ?? ''));
        $rowNo = trim((string) ($rowNo ?? ''));

        if ($lane === '' || $rack === '' || $col === '' || $rowNo === '') {
            return '';
        }

        return "{$lane}-{$rack}-{$col}-{$rowNo}";
    }

    private function getValue($row, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (is_array($row) && array_key_exists($key, $row)) {
                return $row[$key];
            }
            if ($row instanceof Collection && $row->has($key)) {
                return $row->get($key);
            }
            if (isset($row[$key])) {
                return $row[$key];
            }
        }
        return null;
    }

    private function hasAnyKey($row, array $keys): bool
    {
        foreach ($keys as $key) {
            if (is_array($row) && array_key_exists($key, $row)) {
                return true;
            }
            if ($row instanceof Collection && $row->has($key)) {
                return true;
            }
            if (isset($row[$key])) {
                return true;
            }
        }
        return false;
    }
}
