<?php

namespace App\Imports;

use App\Models\Category;
use App\Models\Item;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ItemBundleImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    /**
     * [bundle_sku => ['bundle' => Item, 'components' => [['component_item_id' => int, 'required_qty' => int]]]]
     */
    public array $groups = [];

    private static array $bundleSkuKeys = ['bundle_sku', 'sku_bundle', 'bundle'];
    private static array $bundleNameKeys = ['bundle_name', 'nama_bundle', 'name', 'nama'];
    private static array $categoryIdKeys = ['category_id', 'kategori_id', 'id_kategori'];
    private static array $parentCategoryKeys = ['parent_category', 'parent_kategori', 'kategori_parent'];
    private static array $categoryKeys = ['category', 'kategori'];
    private static array $componentSkuKeys = ['component_sku', 'sku_komponen', 'komponen', 'sku_component', 'component'];
    private static array $qtyKeys = ['required_qty', 'qty', 'jumlah', 'quantity'];

    public function collection(Collection $rows): void
    {
        $bundleCache = [];
        $bundleCategoryBySku = [];
        $componentCache = [];

        foreach ($rows as $row) {
            $row = $row->toArray();

            $bundleSku = $this->detect($row, self::$bundleSkuKeys);
            $bundleName = $this->detectRawString($row, self::$bundleNameKeys);
            $categoryId = $this->detectCategoryId($row);
            $componentSku = $this->detect($row, self::$componentSkuKeys);
            $qty = $this->detectQty($row);

            if ($bundleSku === '' || $componentSku === '') {
                continue;
            }

            if ($categoryId !== null) {
                if (array_key_exists($bundleSku, $bundleCategoryBySku) && $bundleCategoryBySku[$bundleSku] !== $categoryId) {
                    throw ValidationException::withMessages([
                        'category' => "Bundle SKU '{$bundleSku}' memiliki kategori berbeda dalam file yang sama.",
                    ]);
                }
                $bundleCategoryBySku[$bundleSku] = $categoryId;
            }

            if (!array_key_exists($bundleSku, $bundleCache)) {
                $bundleCache[$bundleSku] = Item::where('sku', $bundleSku)->first(['id', 'sku', 'name', 'item_type', 'category_id']);
            }
            $bundle = $bundleCache[$bundleSku];

            if (!$bundle) {
                $bundle = Item::create([
                    'sku' => $bundleSku,
                    'name' => $bundleName !== '' ? $bundleName : $bundleSku,
                    'item_type' => Item::TYPE_BUNDLE,
                    'category_id' => $categoryId ?? 0,
                ]);
                $bundleCache[$bundleSku] = $bundle;
            }
            if (!$bundle->isBundle()) {
                throw ValidationException::withMessages([
                    'bundle_sku' => "SKU '{$bundleSku}' bukan item bundle.",
                ]);
            }
            if ($categoryId !== null && (int) ($bundle->category_id ?? 0) !== $categoryId) {
                $bundle->category_id = $categoryId;
                $bundle->save();
                $bundleCache[$bundleSku] = $bundle;
            }

            if (!array_key_exists($componentSku, $componentCache)) {
                $componentCache[$componentSku] = Item::where('sku', $componentSku)->first(['id', 'sku', 'name', 'item_type']);
            }
            $component = $componentCache[$componentSku];

            if (!$component) {
                throw ValidationException::withMessages([
                    'component_sku' => "Komponen SKU '{$componentSku}' tidak ditemukan di master item.",
                ]);
            }

            if (!isset($this->groups[$bundleSku])) {
                $this->groups[$bundleSku] = [
                    'bundle' => $bundle,
                    'components' => [],
                ];
            }

            $this->groups[$bundleSku]['components'][] = [
                'component_item_id' => $component->id,
                'required_qty' => $qty,
            ];
        }
    }

    private function detect(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            if (isset($row[$key]) && trim((string) $row[$key]) !== '') {
                return strtoupper(trim((string) $row[$key]));
            }
        }
        return '';
    }

    private function detectRaw(array $row, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
                return $row[$key];
            }
        }
        return null;
    }

    private function detectRawString(array $row, array $keys): string
    {
        $value = $this->detectRaw($row, $keys);

        return $value === null ? '' : trim((string) $value);
    }

    private function detectCategoryId(array $row): ?int
    {
        $rawCategoryId = $this->detectRaw($row, self::$categoryIdKeys);
        if ($rawCategoryId !== null && trim((string) $rawCategoryId) !== '') {
            if (!is_numeric($rawCategoryId) || (float) $rawCategoryId !== (float) ((int) $rawCategoryId) || (int) $rawCategoryId < 0) {
                throw ValidationException::withMessages([
                    'category_id' => 'category_id harus berupa angka bulat minimal 0.',
                ]);
            }

            $categoryId = (int) $rawCategoryId;
            if ($categoryId > 0 && !Category::where('id', $categoryId)->exists()) {
                throw ValidationException::withMessages([
                    'category_id' => "Kategori ID '{$categoryId}' tidak ditemukan.",
                ]);
            }

            return $categoryId;
        }

        $categoryName = $this->detectRawString($row, self::$categoryKeys);
        if ($categoryName === '') {
            return null;
        }

        $parentCategoryName = $this->detectRawString($row, self::$parentCategoryKeys);
        $parentCategoryId = 0;
        if ($parentCategoryName !== '') {
            $parentCategory = $this->findOrCreateCategory($parentCategoryName, 0);
            $parentCategoryId = $parentCategory?->id ?? 0;
        }

        $category = $this->findOrCreateCategory($categoryName, $parentCategoryId);
        return $category?->id;
    }

    private function detectQty(array $row): int
    {
        $raw = $this->detectRaw($row, self::$qtyKeys);
        if ($raw === null || trim((string) $raw) === '') {
            throw ValidationException::withMessages([
                'required_qty' => 'required_qty wajib diisi untuk setiap komponen bundle.',
            ]);
        }

        if (!is_numeric($raw) || (float) $raw !== (float) ((int) $raw) || (int) $raw < 1) {
            throw ValidationException::withMessages([
                'required_qty' => 'required_qty harus berupa angka bulat minimal 1.',
            ]);
        }

        return (int) $raw;
    }

    private function findOrCreateCategory(string $name, int $parentId = 0): ?Category
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            return null;
        }

        $normalized = mb_strtolower($trimmed);
        $category = Category::whereRaw('LOWER(name) = ?', [$normalized])->first();
        if ($category) {
            if ($parentId !== 0 && (int) $category->parent_id !== $parentId) {
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
}
