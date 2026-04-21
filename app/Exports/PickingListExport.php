<?php

namespace App\Exports;

use App\Models\QcScanException;
use App\Models\PickingList;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PickingListExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(private array $filters = [])
    {
    }

    public function collection(): Collection
    {
        $query = PickingList::query()
            ->with('item', 'item.location.area', 'item.area')
            ->orderBy('list_date', 'desc')
            ->orderBy('sku');
        $query->whereNotIn('sku', QcScanException::query()->select('sku'));

        $search = trim((string) ($this->filters['q'] ?? ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('sku', 'like', "%{$search}%")
                    ->orWhereHas('item', function ($itemQ) use ($search) {
                        $itemQ->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $date = $this->filters['date'] ?? null;
        if (empty($date)) {
            $date = now()->toDateString();
        }
        try {
            $target = Carbon::parse($date)->toDateString();
            $query->whereDate('list_date', $target);
        } catch (\Throwable) {
            // ignore invalid date
        }

        $status = (string) ($this->filters['status'] ?? '');
        if ($status === 'ongoing') {
            $query->where('remaining_qty', '>', 0);
        } elseif ($status === 'done') {
            $query->where('remaining_qty', '<=', 0);
        }

        $areaId = $this->filters['area_id'] ?? null;
        if (!empty($areaId)) {
            $query->whereHas('item', function ($itemQ) use ($areaId) {
                $itemQ->where('area_id', (int) $areaId);
            });
        }

        return $query->get();
    }

    public function headings(): array
    {
        return ['SKU', 'Nama', 'Area', 'Alamat', 'Qty', 'Remaining'];
    }

    public function map($row): array
    {
        $item = $row->item;
        $area = $item?->resolvedArea();
        $address = $item?->resolvedAddress() ?: '-';
        return [
            $row->sku ?? '-',
            $item?->name ?? '-',
            $area?->code ?? '-',
            $address,
            (int) $row->qty,
            (int) $row->remaining_qty,
        ];
    }
}
