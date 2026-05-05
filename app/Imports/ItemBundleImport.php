<?php

namespace App\Imports;

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
    private static array $componentSkuKeys = ['component_sku', 'sku_komponen', 'komponen', 'sku_component', 'component'];
    private static array $qtyKeys = ['required_qty', 'qty', 'jumlah', 'quantity'];

    public function collection(Collection $rows): void
    {
        $bundleCache = [];
        $componentCache = [];

        foreach ($rows as $row) {
            $row = $row->toArray();

            $bundleSku = $this->detect($row, self::$bundleSkuKeys);
            $bundleName = $this->detectRawString($row, self::$bundleNameKeys);
            $componentSku = $this->detect($row, self::$componentSkuKeys);
            $qty = $this->detectQty($row);

            if ($bundleSku === '' || $componentSku === '') {
                continue;
            }

            if (!array_key_exists($bundleSku, $bundleCache)) {
                $bundleCache[$bundleSku] = Item::where('sku', $bundleSku)->first(['id', 'sku', 'name', 'item_type']);
            }
            $bundle = $bundleCache[$bundleSku];

            if (!$bundle) {
                $bundle = Item::create([
                    'sku' => $bundleSku,
                    'name' => $bundleName !== '' ? $bundleName : $bundleSku,
                    'item_type' => Item::TYPE_BUNDLE,
                    'category_id' => 0,
                ]);
                $bundleCache[$bundleSku] = $bundle;
            }
            if (!$bundle->isBundle()) {
                throw ValidationException::withMessages([
                    'bundle_sku' => "SKU '{$bundleSku}' bukan item bundle.",
                ]);
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
}
