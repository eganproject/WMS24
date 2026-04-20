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
            ->with('item', 'item.location.lane', 'item.lane')
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

        $laneId = $this->filters['lane_id'] ?? null;
        if (!empty($laneId)) {
            $query->whereHas('item', function ($itemQ) use ($laneId) {
                $itemQ->where('lane_id', (int) $laneId);
            });
        } else {
            $divisiId = $this->filters['divisi_id'] ?? null;
            if (!empty($divisiId)) {
                $query->whereHas('item.lane', function ($laneQ) use ($divisiId) {
                    $laneQ->where('divisi_id', (int) $divisiId);
                });
            }
        }

        return $query->get();
    }

    public function headings(): array
    {
        return ['SKU', 'Nama', 'Lane', 'Alamat', 'Qty', 'Remaining'];
    }

    public function map($row): array
    {
        $item = $row->item;
        $lane = $item?->resolvedLane();
        $address = $item?->resolvedAddress() ?: '-';
        return [
            $row->sku ?? '-',
            $item?->name ?? '-',
            $lane?->code ?? '-',
            $address,
            (int) $row->qty,
            (int) $row->remaining_qty,
        ];
    }
}
