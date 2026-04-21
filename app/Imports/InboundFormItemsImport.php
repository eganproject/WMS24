<?php

namespace App\Imports;

use App\Models\Item;
use App\Support\InboundScanExpectation;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class InboundFormItemsImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    /** @var array<int,array{item_id:int,qty:int,koli:int,note:?string,sku:string,name:string}> */
    public array $items = [];

    public function collection(Collection $rows)
    {
        if ($rows->isEmpty()) {
            throw ValidationException::withMessages([
                'file' => 'File kosong',
            ]);
        }

        $headers = array_keys($rows->first()?->toArray() ?? []);
        if (!in_array('sku', $headers, true)) {
            throw ValidationException::withMessages([
                'file' => 'Header wajib: sku, qty/koli. Opsional: item_note atau note.',
            ]);
        }

        $qtyKey = $this->detectQtyKey($headers);
        $koliKey = $this->detectKoliKey($headers);
        if ($qtyKey === null && $koliKey === null) {
            throw ValidationException::withMessages([
                'file' => 'Header qty/koli wajib (gunakan: qty/quantity/jumlah/stok/stock atau koli/kolian/isi_koli).',
            ]);
        }

        $skuInputs = $rows->map(fn ($row) => $this->normalizeSku((string) ($row['sku'] ?? '')))
            ->filter()
            ->unique()
            ->values();

        $itemMap = Item::query()
            ->get(['id', 'sku', 'name', 'koli_qty'])
            ->mapWithKeys(fn (Item $item) => [$this->normalizeSku((string) $item->sku) => $item]);

        $missing = [];
        $errors = [];
        $aggregated = [];
        $rowIndex = 1;

        foreach ($rows as $row) {
            $rowIndex++;
            $normalizedSku = $this->normalizeSku((string) ($row['sku'] ?? ''));
            if ($normalizedSku === '') {
                continue;
            }

            /** @var Item|null $item */
            $item = $itemMap->get($normalizedSku);
            if (!$item) {
                $missing[$normalizedSku] = true;
                continue;
            }

            $qty = $qtyKey ? $this->parseQty($row, $qtyKey) : 0;
            $koli = $koliKey ? $this->parseQty($row, $koliKey) : 0;

            if ($qty <= 0 && $koli > 0) {
                $koliQty = (int) ($item->koli_qty ?? 0);
                if ($koliQty <= 0) {
                    $errors[] = "Baris {$rowIndex}: isi per koli belum diisi untuk SKU {$item->sku}";
                    continue;
                }

                $qty = $koli * $koliQty;
            }

            if ($qty <= 0) {
                $errors[] = "Baris {$rowIndex}: qty/koli tidak valid untuk SKU {$item->sku}";
                continue;
            }

            try {
                $resolved = InboundScanExpectation::resolve($item, $qty, $koli > 0 ? $koli : null);
            } catch (ValidationException $e) {
                $errors[] = "Baris {$rowIndex}: ".(collect($e->errors())->flatten()->first() ?? $e->getMessage());
                continue;
            }

            $itemId = (int) $item->id;
            $itemNote = trim((string) ($row['item_note'] ?? $row['note_item'] ?? $row['note'] ?? ''));

            if (!isset($aggregated[$itemId])) {
                $aggregated[$itemId] = [
                    'item_id' => $itemId,
                    'qty' => $resolved['qty'],
                    'koli' => $resolved['koli'],
                    'note' => $itemNote !== '' ? $itemNote : null,
                    'sku' => (string) $item->sku,
                    'name' => (string) $item->name,
                ];
                continue;
            }

            $aggregated[$itemId]['qty'] += $resolved['qty'];
            $aggregated[$itemId]['koli'] += $resolved['koli'];
            if ($itemNote !== '' && empty($aggregated[$itemId]['note'])) {
                $aggregated[$itemId]['note'] = $itemNote;
            }
        }

        if (!empty($missing)) {
            throw ValidationException::withMessages([
                'file' => 'SKU tidak ditemukan: '.implode(', ', array_keys($missing)),
            ]);
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages([
                'file' => implode(' | ', array_slice($errors, 0, 5)),
            ]);
        }

        $this->items = collect($aggregated)
            ->sortBy('sku', SORT_NATURAL)
            ->values()
            ->all();

        if (empty($this->items)) {
            throw ValidationException::withMessages([
                'file' => 'Tidak ada item valid untuk diimport.',
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

    private function normalizeSku(string $value): string
    {
        return mb_strtoupper(trim($value));
    }
}
