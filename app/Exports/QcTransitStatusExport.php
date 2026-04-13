<?php

namespace App\Exports;

use App\Models\QcResiScan;
use App\Support\QcTransitStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class QcTransitStatusExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithColumnFormatting
{
    public function __construct(private array $filters = [])
    {
    }

    public function collection(): Collection
    {
        $query = QcResiScan::query()
            ->with(['resi', 'scanner', 'completer'])
            ->select('qc_resi_scans.*')
            ->selectSub(function ($sub) {
                $sub->from('packer_resi_scans')
                    ->selectRaw('count(1)')
                    ->whereColumn('packer_resi_scans.resi_id', 'qc_resi_scans.resi_id');
            }, 'packer_scan_count')
            ->orderByDesc('started_at')
            ->orderByDesc('id');

        $search = trim((string) ($this->filters['q'] ?? ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('scan_code', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhereHas('resi', function ($resiQ) use ($search) {
                        $resiQ->where('id_pesanan', 'like', "%{$search}%")
                            ->orWhere('no_resi', 'like', "%{$search}%");
                    })
                    ->orWhereHas('scanner', function ($userQ) use ($search) {
                        $userQ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('completer', function ($userQ) use ($search) {
                        $userQ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $date = $this->filters['date'] ?? null;
        if (empty($date)) {
            $date = now()->toDateString();
        }
        try {
            $target = Carbon::parse($date)->toDateString();
            $query->whereDate('started_at', $target);
        } catch (\Throwable) {
            // ignore invalid date
        }

        $status = (string) ($this->filters['status'] ?? '');
        if ($status === QcTransitStatus::DRAFT) {
            $query->where('status', QcTransitStatus::DRAFT);
        } elseif ($status === QcTransitStatus::HOLD) {
            $query->where('status', QcTransitStatus::HOLD);
        } elseif ($status === QcTransitStatus::NEXT_READY_PACKING) {
            $query->where('status', QcTransitStatus::PASSED)
                ->whereNotExists(function ($sub) {
                    $sub->selectRaw('1')
                        ->from('packer_resi_scans')
                        ->whereColumn('packer_resi_scans.resi_id', 'qc_resi_scans.resi_id');
                });
        } elseif ($status === QcTransitStatus::NEXT_FORWARDED) {
            $query->whereExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('packer_resi_scans')
                    ->whereColumn('packer_resi_scans.resi_id', 'qc_resi_scans.resi_id');
            });
        }

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'Waktu Mulai',
            'Waktu Selesai',
            'ID Pesanan',
            'No Resi',
            'Status QC',
            'Status Lanjutan',
            'QC Oleh',
            'Selesai Oleh',
            'Kode Scan',
        ];
    }

    public function map($row): array
    {
        $nextStage = QcTransitStatus::nextStageKey(
            $row->status,
            (int) ($row->packer_scan_count ?? 0) > 0
        );

        return [
            $row->started_at?->format('Y-m-d H:i') ?? '-',
            $row->completed_at?->format('Y-m-d H:i') ?? '-',
            (string) ($row->resi?->id_pesanan ?? '-'),
            (string) ($row->resi?->no_resi ?? '-'),
            QcTransitStatus::scanStatusLabel($row->status),
            QcTransitStatus::nextStageLabel($nextStage),
            (string) ($row->scanner?->name ?? '-'),
            (string) ($row->completer?->name ?? '-'),
            (string) ($row->scan_code ?? '-'),
        ];
    }

    public function columnFormats(): array
    {
        return [
            'C' => NumberFormat::FORMAT_TEXT,
            'D' => NumberFormat::FORMAT_TEXT,
        ];
    }
}
