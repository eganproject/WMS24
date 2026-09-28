<?php

namespace App\Imports;

use App\Models\Category;
use App\Models\Item;
use App\Support\ItemBarcodeResolver;
use App\Support\ItemUpdateFields;
use App\Support\LocationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ItemUpdatesImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    public int $processed = 0;
    public int $updated = 0;
    public int $unchanged = 0;

    private array $fields;
    private array $seenSkus = [];

    public function __construct(array $fields)
    {
        $this->fields = ItemUpdateFields::normalize($fields);
    }

    public function fields(): array
    {
        return $this->fields;
    }

    public function headingRow(): int
    {
        return 4;
    }

    public function collection(Collection $rows): void
    {
        if ($rows->isEmpty()) {
            throw ValidationException::withMessages(['file' => 'File update item kosong.']);
        }

        $headers = array_values(array_filter(
            array_keys($rows->first()?->toArray() ?? []),
            fn ($header) => $header !== null && $header !== ''
        ));
        $expectedHeaders = ItemUpdateFields::headings($this->fields);
        $missing = array_diff($expectedHeaders, $headers);
        $unexpected = array_diff($headers, $expectedHeaders);
        if ($missing !== [] || $unexpected !== []) {
            $parts = [];
            if ($missing !== []) {
                $parts[] = 'header kurang: '.implode(', ', $missing);
            }
            if ($unexpected !== []) {
                $parts[] = 'header tidak diizinkan: '.implode(', ', $unexpected);
            }
            throw ValidationException::withMessages([
                'file' => 'Template tidak sesuai pengaturan field ('.implode('; ', $parts).'). Unduh ulang template dari halaman Items.',
            ]);
        }

        $excelRow = 5;
        foreach ($rows as $row) {
            $sku = trim((string) ($row['sku'] ?? ''));
            if ($sku === '') {
                throw ValidationException::withMessages([
                    'file' => "Baris {$excelRow}: SKU wajib diisi sebagai kunci pencarian.",
                ]);
            }

            $normalizedSku = ItemBarcodeResolver::normalize($sku);
            if (isset($this->seenSkus[$normalizedSku])) {
                throw ValidationException::withMessages([
                    'file' => "Baris {$excelRow}: SKU {$sku} duplikat di file (sudah ada pada baris {$this->seenSkus[$normalizedSku]}).",
                ]);
            }
            $this->seenSkus[$normalizedSku] = $excelRow;

            $item = Item::query()
                ->whereRaw('LOWER(sku) = ?', [$normalizedSku])
                ->first();
            if (!$item) {
                throw ValidationException::withMessages([
                    'file' => "Baris {$excelRow}: SKU {$sku} tidak ditemukan. Import update tidak membuat item baru.",
                ]);
            }

            $payload = $this->payloadForRow($row, $item, $excelRow);
            $item->fill($payload);
            $this->processed++;
            if ($item->isDirty()) {
                $item->save();
                $this->updated++;
            } else {
                $this->unchanged++;
            }

            $excelRow++;
        }
    }

    private function payloadForRow(Collection $row, Item $item, int $excelRow): array
    {
        $payload = [];
        foreach ($this->fields as $field) {
            switch ($field) {
                case 'name':
                    $this->applyName($payload, $row, $item, $excelRow);
                    break;
                case 'status':
                    $this->applyStatus($payload, $row, $item, $excelRow);
                    break;
                case 'category':
                    $payload['category_id'] = $this->resolveCategoryId($row, $item, $excelRow);
                    break;
                case 'address':
                    $this->applyAddress($payload, $row);
                    break;
                case 'description':
                    $payload['description'] = $this->nullableTrim($row['description'] ?? null);
                    break;
                case 'safety_stock':
                    $payload['safety_stock'] = $this->parseSafetyStock($row, $item, $excelRow);
                    break;
            }
        }

        return $payload;
    }

    private function applyName(array &$payload, Collection $row, Item $item, int $excelRow): void
    {
        $name = trim((string) ($row['name'] ?? ''));
        if ($name === '') {
            $this->fail($excelRow, $item, 'name tidak boleh kosong.');
        }
        if (Str::length($name) > 150) {
            $this->fail($excelRow, $item, 'name maksimal 150 karakter.');
        }
        $payload['name'] = $name;
    }

    private function applyStatus(array &$payload, Collection $row, Item $item, int $excelRow): void
    {
        $status = strtolower(trim((string) ($row['status'] ?? '')));
        if (!in_array($status, [Item::STATUS_ACTIVE, Item::STATUS_INACTIVE], true)) {
            $this->fail($excelRow, $item, 'status harus active atau inactive.');
        }
        $payload['status'] = $status;
    }

    private function resolveCategoryId(Collection $row, Item $item, int $excelRow): int
    {
        $parentName = trim((string) ($row['parent_category'] ?? ''));
        $categoryName = trim((string) ($row['category'] ?? ''));
        if ($categoryName === '') {
            if ($parentName !== '') {
                $this->fail($excelRow, $item, 'category wajib diisi jika parent_category diisi.');
            }
            return 0;
        }

        $parentId = 0;
        if ($parentName !== '') {
            $parent = Category::query()
                ->where('parent_id', 0)
                ->whereRaw('LOWER(name) = ?', [Str::lower($parentName)])
                ->first();
            if (!$parent) {
                $this->fail($excelRow, $item, "parent_category {$parentName} tidak ditemukan.");
            }
            $parentId = (int) $parent->id;
        }

        $category = Category::query()
            ->where('parent_id', $parentId)
            ->whereRaw('LOWER(name) = ?', [Str::lower($categoryName)])
            ->first();
        if (!$category) {
            $path = $parentName !== '' ? "{$parentName} > {$categoryName}" : $categoryName;
            $this->fail($excelRow, $item, "category {$path} tidak ditemukan.");
        }

        return (int) $category->id;
    }

    private function applyAddress(array &$payload, Collection $row): void
    {
        $address = trim((string) ($row['address'] ?? ''));
        if ($address === '') {
            $payload['area_id'] = null;
            $payload['location_id'] = null;
            $payload['address'] = null;
            return;
        }

        $location = LocationService::resolveLocation($address);
        if ($location) {
            $payload['area_id'] = $location->area_id;
            $payload['location_id'] = $location->id;
            $payload['address'] = $location->code;
            return;
        }

        $area = LocationService::resolveArea($address);
        $payload['area_id'] = $area?->id;
        $payload['location_id'] = null;
        $payload['address'] = $area?->code ?? $address;
    }

    private function parseSafetyStock(Collection $row, Item $item, int $excelRow): int
    {
        $raw = trim((string) ($row['safety_stock'] ?? ''));
        if ($raw === '') {
            return 0;
        }
        $value = filter_var($raw, FILTER_VALIDATE_INT);
        if ($value === false || $value < 0) {
            $this->fail($excelRow, $item, 'safety_stock harus bilangan bulat minimal 0.');
        }

        return (int) $value;
    }

    private function nullableTrim(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
    }

    private function fail(int $excelRow, Item $item, string $message): never
    {
        throw ValidationException::withMessages([
            'file' => "Baris {$excelRow} (SKU {$item->sku}): {$message}",
        ]);
    }
}
