<?php

namespace App\Imports;

use App\Models\Item;
use App\Models\ItemBarcode;
use App\Support\ItemBarcodeResolver;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ItemBarcodesImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    public int $created = 0;
    public int $updated = 0;

    /** @var array<string,string> */
    private array $seen = [];

    public function collection(Collection $rows): void
    {
        if ($rows->isEmpty()) {
            throw ValidationException::withMessages([
                'file' => 'File kosong',
            ]);
        }

        $first = $rows->first();
        $headers = array_keys($first?->toArray() ?? []);
        $missing = array_diff(['sku', 'barcode'], $headers);
        if ($missing) {
            $detected = implode(', ', array_filter($headers, fn ($h) => $h !== null && $h !== ''));
            throw ValidationException::withMessages([
                'file' => 'Header wajib: sku, barcode. Header terdeteksi: '.($detected !== '' ? $detected : '-').'.',
            ]);
        }

        $excelRow = 2;
        foreach ($rows as $row) {
            $sku = trim((string) ($row['sku'] ?? ''));
            $barcode = trim((string) ($row['barcode'] ?? ''));
            if ($sku === '' || $barcode === '') {
                $excelRow++;
                continue;
            }

            $item = Item::query()
                ->whereRaw('LOWER(sku) = ?', [ItemBarcodeResolver::normalize($sku)])
                ->first(['id', 'sku', 'name', 'item_type']);

            if (!$item || !$item->isSingle()) {
                throw ValidationException::withMessages([
                    'file' => "Baris {$excelRow}: SKU {$sku} tidak ditemukan atau bukan item stok fisik.",
                ]);
            }

            $normalized = ItemBarcodeResolver::normalize($barcode);
            $hash = ItemBarcodeResolver::hash($normalized);

            if (isset($this->seen[$hash]) && $this->seen[$hash] !== (string) $item->id) {
                throw ValidationException::withMessages([
                    'file' => "Baris {$excelRow}: barcode {$barcode} juga dipakai untuk SKU lain di file yang sama.",
                ]);
            }
            if (isset($this->seen[$hash]) && $this->seen[$hash] === (string) $item->id) {
                throw ValidationException::withMessages([
                    'file' => "Baris {$excelRow}: barcode {$barcode} duplikat untuk SKU {$item->sku} di file yang sama.",
                ]);
            }
            $this->seen[$hash] = (string) $item->id;

            if ($normalized === ItemBarcodeResolver::normalize($item->sku)) {
                throw ValidationException::withMessages([
                    'file' => "Baris {$excelRow} (SKU {$item->sku}): barcode eksternal tidak boleh sama dengan SKU item.",
                ]);
            }

            $skuConflict = Item::query()
                ->whereRaw('LOWER(sku) = ?', [$normalized])
                ->where('id', '!=', $item->id)
                ->exists();
            if ($skuConflict) {
                throw ValidationException::withMessages([
                    'file' => "Baris {$excelRow} (SKU {$item->sku}): barcode {$barcode} bentrok dengan SKU item lain.",
                ]);
            }

            $existing = ItemBarcode::query()->where('normalized_hash', $hash)->first();
            if ($existing && (int) $existing->item_id !== (int) $item->id) {
                throw ValidationException::withMessages([
                    'file' => "Baris {$excelRow} (SKU {$item->sku}): barcode {$barcode} sudah digunakan item lain.",
                ]);
            }

            $payload = [
                'item_id' => $item->id,
                'barcode_value' => $barcode,
                'normalized_barcode' => $normalized,
                'normalized_hash' => $hash,
                'source_name' => $this->nullableTrim($row['source_name'] ?? null),
                'note' => $this->nullableTrim($row['note'] ?? null),
                'is_active' => true,
            ];

            if ($existing) {
                $existing->update($payload);
                $this->updated++;
            } else {
                ItemBarcode::create($payload);
                $this->created++;
            }

            $excelRow++;
        }
    }

    private function nullableTrim(mixed $value): ?string
    {
        $trimmed = trim((string) ($value ?? ''));
        return $trimmed === '' ? null : $trimmed;
    }
}
