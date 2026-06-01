<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class WeeklyScheduleTemplatesImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    /** @var array<int,array<string,mixed>> */
    public array $templates = [];

    /** @var array<int,string> */
    private array $requiredHeaders = [
        'template_name',
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
                'file' => 'Header wajib: template_name. Header format lengkap: template_name, is_active, day_of_week, day_name, schedule_type, shift, work_shift_id. '
                    .($detected !== '' ? 'Header terdeteksi: '.$detected : ''),
            ]);
        }

        $errors = [];
        $grouped = [];
        $rowIndex = 1;

        foreach ($rows as $row) {
            $rowIndex++;
            $rowData = $this->normalizeRow($row);
            $templateName = trim((string) ($rowData['template_name'] ?? ''));
            $scheduleType = $this->normalizeScheduleType($rowData['schedule_type'] ?? 'day_off');
            $dayOfWeek = $this->resolveDayOfWeek($rowData['day_of_week'] ?? null, $rowData['day_name'] ?? null);
            $shiftRaw = trim((string) ($rowData['work_shift_id'] ?? $rowData['shift'] ?? ''));

            if ($templateName === '') {
                $errors[] = "Baris {$rowIndex}: template_name wajib diisi";
                continue;
            }

            if ($dayOfWeek === null) {
                $errors[] = "Baris {$rowIndex}: day_of_week/day_name harus berisi hari Senin-Minggu atau angka 1-7";
                continue;
            }

            if (!in_array($scheduleType, ['work', 'day_off', 'holiday', 'leave'], true)) {
                $errors[] = "Baris {$rowIndex}: schedule_type harus work/masuk, day_off/libur, holiday, atau leave";
                continue;
            }

            if ($scheduleType === 'work' && $shiftRaw === '') {
                $errors[] = "Baris {$rowIndex}: shift atau work_shift_id wajib diisi untuk schedule_type work";
                continue;
            }

            $key = mb_strtolower($templateName);
            $grouped[$key] ??= [
                'name' => $templateName,
                'is_active' => $this->normalizeBoolean($rowData['is_active'] ?? true),
                'days' => [],
                'day_rows' => [],
            ];

            if (isset($grouped[$key]['day_rows'][$dayOfWeek])) {
                $errors[] = "Baris {$rowIndex}: hari {$dayOfWeek} duplikat untuk template {$templateName}";
                continue;
            }

            $grouped[$key]['day_rows'][$dayOfWeek] = true;
            $grouped[$key]['days'][] = [
                'row' => $rowIndex,
                'day_of_week' => $dayOfWeek,
                'schedule_type' => $scheduleType,
                'shift_raw' => $shiftRaw,
            ];
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages([
                'file' => implode(' | ', array_slice($errors, 0, 5)),
            ]);
        }

        $this->templates = array_values(array_map(function (array $template) {
            unset($template['day_rows']);
            return $template;
        }, $grouped));

        if (empty($this->templates)) {
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
            'nama_template', 'template', 'name', 'nama' => 'template_name',
            'aktif', 'status', 'status_template' => 'is_active',
            'hari_ke', 'nomor_hari' => 'day_of_week',
            'hari', 'nama_hari' => 'day_name',
            'tipe_jadwal', 'tipe', 'jenis_jadwal' => 'schedule_type',
            'nama_shift', 'shift_name' => 'shift',
            'shift_id', 'id_shift' => 'work_shift_id',
            default => $key,
        };
    }

    private function normalizeScheduleType(mixed $value): string
    {
        $type = mb_strtolower(trim((string) $value));
        $type = preg_replace('/[^\p{L}\p{N}]+/u', '_', $type);
        $type = trim((string) $type, '_');

        return match ($type) {
            '', 'day_off', 'off', 'libur', 'tidak_masuk' => 'day_off',
            'work', 'masuk', 'kerja' => 'work',
            'holiday', 'hari_libur', 'libur_nasional' => 'holiday',
            'leave', 'cuti', 'izin' => 'leave',
            default => $type,
        };
    }

    private function resolveDayOfWeek(mixed $dayOfWeek, mixed $dayName): ?int
    {
        $numericDay = trim((string) $dayOfWeek);
        if ($numericDay !== '' && is_numeric($numericDay)) {
            $day = (int) $numericDay;
            return $day >= 1 && $day <= 7 ? $day : null;
        }

        $day = mb_strtolower(trim((string) ($dayName ?: $dayOfWeek)));
        $day = preg_replace('/[^\p{L}\p{N}]+/u', '_', $day);
        $day = trim((string) $day, '_');

        return match ($day) {
            'senin', 'monday', 'mon' => 1,
            'selasa', 'tuesday', 'tue' => 2,
            'rabu', 'wednesday', 'wed' => 3,
            'kamis', 'thursday', 'thu' => 4,
            'jumat', 'jum_at', 'friday', 'fri' => 5,
            'sabtu', 'saturday', 'sat' => 6,
            'minggu', 'sunday', 'sun' => 7,
            default => null,
        };
    }

    private function normalizeBoolean(mixed $value): bool
    {
        $normalized = mb_strtolower(trim((string) $value));
        $normalized = preg_replace('/[^\p{L}\p{N}]+/u', '_', $normalized);
        $normalized = trim((string) $normalized, '_');

        return !in_array($normalized, ['0', 'inactive', 'nonaktif', 'non_aktif', 'tidak', 'no', 'false'], true);
    }
}
