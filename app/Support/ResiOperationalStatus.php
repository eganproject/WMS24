<?php

namespace App\Support;

class ResiOperationalStatus
{
    public const CANCELED = 'canceled';
    public const PENDING_QC = 'pending_qc';
    public const QC_IN_PROGRESS = 'qc_in_progress';
    public const QC_PASSED = 'qc_passed';
    public const PACKED = 'packed';
    public const SCAN_OUT_DONE = 'scan_out_done';

    public static function resolve(
        ?string $businessStatus,
        bool $hasQc,
        bool $qcPassed,
        bool $hasPackerScan,
        bool $hasScanOut
    ): string {
        if (($businessStatus ?? 'active') === 'canceled') {
            return self::CANCELED;
        }

        if ($hasScanOut) {
            return self::SCAN_OUT_DONE;
        }

        if ($hasPackerScan) {
            return self::PACKED;
        }

        if ($qcPassed) {
            return self::QC_PASSED;
        }

        if ($hasQc) {
            return self::QC_IN_PROGRESS;
        }

        return self::PENDING_QC;
    }

    public static function labels(): array
    {
        return [
            self::CANCELED => 'Cancel',
            self::PENDING_QC => 'Menunggu QC',
            self::QC_IN_PROGRESS => 'QC Berjalan',
            self::QC_PASSED => 'Lolos QC',
            self::PACKED => 'Siap Scan Out',
            self::SCAN_OUT_DONE => 'Scan Out Selesai',
        ];
    }

    public static function badgeClass(string $status): string
    {
        return match ($status) {
            self::CANCELED => 'badge-light-danger',
            self::PENDING_QC => 'badge-light-warning',
            self::QC_IN_PROGRESS => 'badge-light-primary',
            self::QC_PASSED => 'badge-light-info',
            self::PACKED => 'badge-light-success',
            self::SCAN_OUT_DONE => 'badge-light-dark',
            default => 'badge-light',
        };
    }

    public static function label(string $status): string
    {
        return self::labels()[$status] ?? $status;
    }

    public static function normalize(?string $status): string
    {
        $status = trim((string) $status);

        return array_key_exists($status, self::labels()) ? $status : '';
    }

    public static function options(): array
    {
        $options = [];
        foreach (self::labels() as $value => $label) {
            $options[] = [
                'value' => $value,
                'label' => $label,
            ];
        }

        return $options;
    }

    public static function applyFilter($query, string $status, string $tableAlias = 'resis'): void
    {
        if ($status === '') {
            return;
        }

        if ($status === self::CANCELED) {
            $query->where($tableAlias.'.status', 'canceled');
            return;
        }

        $query->where(function ($activeQuery) use ($tableAlias) {
            $activeQuery->whereNull($tableAlias.'.status')
                ->orWhere($tableAlias.'.status', '!=', 'canceled');
        });

        if ($status === self::SCAN_OUT_DONE) {
            $query->whereExists(function ($sub) use ($tableAlias) {
                $sub->selectRaw('1')
                    ->from('packer_scan_outs')
                    ->whereColumn('packer_scan_outs.resi_id', $tableAlias.'.id');
            });
            return;
        }

        if ($status === self::PACKED) {
            $query->whereExists(function ($sub) use ($tableAlias) {
                $sub->selectRaw('1')
                    ->from('packer_resi_scans')
                    ->whereColumn('packer_resi_scans.resi_id', $tableAlias.'.id');
            })->whereNotExists(function ($sub) use ($tableAlias) {
                $sub->selectRaw('1')
                    ->from('packer_scan_outs')
                    ->whereColumn('packer_scan_outs.resi_id', $tableAlias.'.id');
            });
            return;
        }

        if ($status === self::QC_PASSED) {
            $query->whereExists(function ($sub) use ($tableAlias) {
                $sub->selectRaw('1')
                    ->from('qc_resi_scans')
                    ->whereColumn('qc_resi_scans.resi_id', $tableAlias.'.id')
                    ->where('qc_resi_scans.status', 'passed');
            })->whereNotExists(function ($sub) use ($tableAlias) {
                $sub->selectRaw('1')
                    ->from('packer_resi_scans')
                    ->whereColumn('packer_resi_scans.resi_id', $tableAlias.'.id');
            })->whereNotExists(function ($sub) use ($tableAlias) {
                $sub->selectRaw('1')
                    ->from('packer_scan_outs')
                    ->whereColumn('packer_scan_outs.resi_id', $tableAlias.'.id');
            });
            return;
        }

        if ($status === self::QC_IN_PROGRESS) {
            $query->whereExists(function ($sub) use ($tableAlias) {
                $sub->selectRaw('1')
                    ->from('qc_resi_scans')
                    ->whereColumn('qc_resi_scans.resi_id', $tableAlias.'.id')
                    ->where('qc_resi_scans.status', '!=', 'passed');
            })->whereNotExists(function ($sub) use ($tableAlias) {
                $sub->selectRaw('1')
                    ->from('packer_resi_scans')
                    ->whereColumn('packer_resi_scans.resi_id', $tableAlias.'.id');
            })->whereNotExists(function ($sub) use ($tableAlias) {
                $sub->selectRaw('1')
                    ->from('packer_scan_outs')
                    ->whereColumn('packer_scan_outs.resi_id', $tableAlias.'.id');
            });
            return;
        }

        if ($status === self::PENDING_QC) {
            $query->whereNotExists(function ($sub) use ($tableAlias) {
                $sub->selectRaw('1')
                    ->from('qc_resi_scans')
                    ->whereColumn('qc_resi_scans.resi_id', $tableAlias.'.id');
            })->whereNotExists(function ($sub) use ($tableAlias) {
                $sub->selectRaw('1')
                    ->from('packer_resi_scans')
                    ->whereColumn('packer_resi_scans.resi_id', $tableAlias.'.id');
            })->whereNotExists(function ($sub) use ($tableAlias) {
                $sub->selectRaw('1')
                    ->from('packer_scan_outs')
                    ->whereColumn('packer_scan_outs.resi_id', $tableAlias.'.id');
            });
        }
    }
}
