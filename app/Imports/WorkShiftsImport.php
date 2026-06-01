<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class WorkShiftsImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    /** @var array<int,array<string,mixed>> */
    public array $rows = [];

    /** @var array<int,string> */
    private array $requiredHeaders = [
        'name',
        'start_time',
        'end_time',
    ];

    public function collection(Collection $rows)
    {
        if ($rows->isEmpty()) {
            throw ValidationException::withMessages([
                'file' => 'File kosong',
            ]);
        }

        $first = $rows->first();
        $headersRaw = array_keys($first?->toArray() ?? []);
        $headers = array_map(fn ($header) => $this->normalizeKey((string) $header), $headersRaw);
        $missing = array_diff($this->requiredHeaders, $headers);

        if (!empty($missing)) {
            $detected = implode(', ', array_filter($headers));
            throw ValidationException::withMessages([
                'file' => 'Header wajib: name, start_time, end_time. Header format lengkap: name, start_time, end_time, break_start_time, break_end_time, late_tolerance_minutes, checkout_tolerance_minutes, overtime_start_after_minutes, minimum_overtime_minutes, crosses_midnight, is_active. '
                    .($detected !== '' ? 'Header terdeteksi: '.$detected : ''),
            ]);
        }

        $errors = [];
        $seenNames = [];
        $rowIndex = 1;

        foreach ($rows as $row) {
            $rowIndex++;
            $rowData = $this->normalizeRow($row);
            $name = trim((string) ($rowData['name'] ?? ''));

            if ($name === '') {
                $errors[] = "Baris {$rowIndex}: name wajib diisi";
                continue;
            }

            $nameKey = mb_strtolower($name);
            if (isset($seenNames[$nameKey])) {
                $errors[] = "Baris {$rowIndex}: nama shift duplikat di file ({$name})";
                continue;
            }
            $seenNames[$nameKey] = true;

            $startTime = $this->normalizeTime($rowData['start_time'] ?? null);
            $endTime = $this->normalizeTime($rowData['end_time'] ?? null);
            $breakStartTime = $this->normalizeTime($rowData['break_start_time'] ?? null, true);
            $breakEndTime = $this->normalizeTime($rowData['break_end_time'] ?? null, true);

            if (!$startTime) {
                $errors[] = "Baris {$rowIndex}: start_time harus format HH:MM";
                continue;
            }

            if (!$endTime) {
                $errors[] = "Baris {$rowIndex}: end_time harus format HH:MM";
                continue;
            }

            if (($rowData['break_start_time'] ?? null) !== null && trim((string) $rowData['break_start_time']) !== '' && !$breakStartTime) {
                $errors[] = "Baris {$rowIndex}: break_start_time harus format HH:MM";
                continue;
            }

            if (($rowData['break_end_time'] ?? null) !== null && trim((string) $rowData['break_end_time']) !== '' && !$breakEndTime) {
                $errors[] = "Baris {$rowIndex}: break_end_time harus format HH:MM";
                continue;
            }

            $this->rows[] = [
                'row' => $rowIndex,
                'name' => $name,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'break_start_time' => $breakStartTime,
                'break_end_time' => $breakEndTime,
                'late_tolerance_minutes' => $this->normalizeMinutes($rowData['late_tolerance_minutes'] ?? 0),
                'checkout_tolerance_minutes' => $this->normalizeMinutes($rowData['checkout_tolerance_minutes'] ?? 0),
                'overtime_start_after_minutes' => $this->normalizeMinutes($rowData['overtime_start_after_minutes'] ?? 0),
                'minimum_overtime_minutes' => $this->normalizeMinutes($rowData['minimum_overtime_minutes'] ?? 0),
                'crosses_midnight' => $this->normalizeBoolean($rowData['crosses_midnight'] ?? false),
                'is_active' => $this->normalizeActive($rowData['is_active'] ?? true),
            ];
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages([
                'file' => implode(' | ', array_slice($errors, 0, 5)),
            ]);
        }

        if (empty($this->rows)) {
            throw ValidationException::withMessages([
                'file' => 'Tidak ada data valid untuk diimport',
            ]);
        }
    }

    private function normalizeRow($row): array
    {
        $data = [];
        foreach (($row instanceof Collection ? $row->toArray() : (array) $row) as $key => $value) {
            $normalizedKey = $this->normalizeKey((string) $key);
            if ($normalizedKey === '') {
                continue;
            }
            $data[$normalizedKey] = $value;
        }

        return $data;
    }

    private function normalizeKey(string $key): string
    {
        $key = ltrim($key, "\xEF\xBB\xBF");
        $key = mb_strtolower(trim($key));
        $key = preg_replace('/[^\p{L}\p{N}]+/u', '_', $key);
        $key = trim((string) $key, '_');

        return match ($key) {
            'nama', 'nama_shift', 'shift', 'shift_name' => 'name',
            'jam_masuk', 'masuk' => 'start_time',
            'jam_pulang', 'pulang' => 'end_time',
            'istirahat_mulai', 'mulai_istirahat' => 'break_start_time',
            'istirahat_selesai', 'selesai_istirahat' => 'break_end_time',
            'toleransi_telat', 'telat' => 'late_tolerance_minutes',
            'toleransi_pulang_cepat', 'pulang_cepat' => 'checkout_tolerance_minutes',
            'lembur_mulai_setelah', 'lembur_setelah' => 'overtime_start_after_minutes',
            'minimal_lembur' => 'minimum_overtime_minutes',
            'shift_malam', 'malam', 'lewat_tengah_malam' => 'crosses_midnight',
            'aktif', 'status', 'status_shift' => 'is_active',
            default => $key,
        };
    }

    private function normalizeTime(mixed $value, bool $nullable = false): ?string
    {
        if ($value === null || $value === '') {
            return $nullable ? null : null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i');
        }

        if (is_numeric($value)) {
            try {
                return Date::excelToDateTimeObject((float) $value)->format('H:i');
            } catch (\Throwable) {
                return null;
            }
        }

        $value = trim((string) $value);
        if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $value, $matches)) {
            $hour = (int) $matches[1];
            $minute = (int) $matches[2];
            if ($hour >= 0 && $hour <= 23 && $minute >= 0 && $minute <= 59) {
                return str_pad((string) $hour, 2, '0', STR_PAD_LEFT).':'.str_pad((string) $minute, 2, '0', STR_PAD_LEFT);
            }
        }

        return null;
    }

    private function normalizeMinutes(mixed $value): int
    {
        return max(0, (int) $value);
    }

    private function normalizeBoolean(mixed $value): bool
    {
        $normalized = mb_strtolower(trim((string) $value));
        $normalized = preg_replace('/[^\p{L}\p{N}]+/u', '_', $normalized);
        $normalized = trim((string) $normalized, '_');

        return in_array($normalized, ['1', 'yes', 'ya', 'y', 'true', 'aktif', 'active', 'malam'], true);
    }

    private function normalizeActive(mixed $value): bool
    {
        $normalized = mb_strtolower(trim((string) $value));
        $normalized = preg_replace('/[^\p{L}\p{N}]+/u', '_', $normalized);
        $normalized = trim((string) $normalized, '_');

        return !in_array($normalized, ['0', 'inactive', 'nonaktif', 'non_aktif', 'tidak', 'no', 'false'], true);
    }
}
