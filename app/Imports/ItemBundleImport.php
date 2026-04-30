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
    private static array $componentSkuKeys = ['component_sku', 'sku_komponen', 'komponen', 'sku_component', 'component'];
    private static array $qtyKeys = ['required_qty', 'qty', 'jumlah', 'quantity'];

    public function collection(Collection $rows): void
    {
        $bundleCache = [];
        $componentCache = [];

        foreach ($rows as $row) {
            $row = $row->toArray();

            $bundleSku = $this->detect($row, self::$bundleSkuKeys);
            $componentSku = $this->detect($row, self::$componentSkuKeys);
            $qty = max(1, (int) ($this->detectRaw($row, self::$qtyKeys) ?? 1));

            if ($bundleSku === '' || $componentSku === '') {
                continue;
            }

            if (!array_key_exists($bundleSku, $bundleCache)) {
                $bundleCache[$bundleSku] = Item::where('sku', $bundleSku)->first(['id', 'sku', 'name', 'item_type']);
            }
            $bundle = $bundleCache[$bundleSku];

            if (!$bundle) {
                throw ValidationException::withMessages([
                    'bundle_sku' => "Bundle SKU '{$bundleSku}' tidak ditemukan di master item.",
                ]);
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
}
