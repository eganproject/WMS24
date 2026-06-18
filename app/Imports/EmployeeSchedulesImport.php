<?php

namespace App\Imports;

use App\Models\EmployeeSchedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class EmployeeSchedulesImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    /** @var array<int,array<string,mixed>> */
    public array $rows = [];

    /** @var array<int,string> */
    private array $requiredHeaders = [
        'schedule_date',
        'schedule_type',
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
        $hasEmployeeIdentifier = array_intersect(['employee_code', 'employee_id', 'employee_name'], $headers);

        if (!empty($missing) || empty($hasEmployeeIdentifier)) {
            $detected = implode(', ', array_filter($headers));
            throw ValidationException::withMessages([
                'file' => 'Header wajib: employee_code, schedule_date, schedule_type. Header import yang disarankan: employee_code, schedule_date, schedule_type, shift, note. '
                    .($detected !== '' ? 'Header terdeteksi: '.$detected : ''),
            ]);
        }

        $errors = [];
        $seen = [];
        $rowIndex = 1;

        foreach ($rows as $row) {
            $rowIndex++;
            $rowData = $this->normalizeRow($row);

            if ($this->isEmptyDataRow($rowData)) {
                continue;
            }

            $employeeCode = trim((string) ($rowData['employee_code'] ?? ''));
            $employeeName = trim((string) ($rowData['employee_name'] ?? ''));
            $employeeId = trim((string) ($rowData['employee_id'] ?? ''));
            if ($employeeCode === '' && $employeeName === '' && $employeeId === '') {
                $errors[] = "Baris {$rowIndex}: employee_code, employee_id, atau employee_name wajib diisi";
                continue;
            }

            $scheduleDate = $this->normalizeDate($rowData['schedule_date'] ?? null);
            if (!$scheduleDate) {
                $errors[] = "Baris {$rowIndex}: schedule_date harus format YYYY-MM-DD";
                continue;
            }

            if (Carbon::parse($scheduleDate)->lt(today())) {
                $errors[] = "Baris {$rowIndex}: tanggal {$scheduleDate} sudah lewat dan tidak bisa diimport";
                continue;
            }

            $scheduleType = $this->normalizeScheduleType($rowData['schedule_type'] ?? null);
            if (!$scheduleType) {
                $errors[] = "Baris {$rowIndex}: schedule_type harus work, day_off, holiday, atau leave";
                continue;
            }

            $key = strtolower(trim($employeeId.'|'.$employeeCode.'|'.$employeeName)).'|'.$scheduleDate;
            if (isset($seen[$key])) {
                $errors[] = "Baris {$rowIndex}: jadwal karyawan pada tanggal {$scheduleDate} duplikat di file";
                continue;
            }
            $seen[$key] = true;

            $workShiftId = trim((string) ($rowData['work_shift_id'] ?? ''));
            $shift = trim((string) ($rowData['shift'] ?? ''));
            if ($scheduleType === EmployeeSchedule::TYPE_WORK && $workShiftId === '' && $shift === '') {
                $errors[] = "Baris {$rowIndex}: jadwal work wajib mengisi shift atau work_shift_id";
                continue;
            }

            $this->rows[] = [
                'row' => $rowIndex,
                'employee_code' => $employeeCode,
                'employee_name' => $employeeName,
                'employee_id' => $employeeId,
                'schedule_date' => $scheduleDate,
                'schedule_type' => $scheduleType,
                'shift' => $shift,
                'work_shift_id' => $workShiftId,
                'note' => trim((string) ($rowData['note'] ?? '')),
            ];
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages([
                'file' => implode(' | ', array_slice($errors, 0, 8)),
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
            'kode_karyawan', 'employee', 'karyawan_code' => 'employee_code',
            'nama_karyawan', 'karyawan', 'name', 'nama' => 'employee_name',
            'id_karyawan' => 'employee_id',
            'tanggal', 'tanggal_jadwal', 'date' => 'schedule_date',
            'tipe', 'tipe_jadwal', 'jenis_jadwal' => 'schedule_type',
            'nama_shift', 'shift_name' => 'shift',
            'id_shift', 'shift_id' => 'work_shift_id',
            'catatan', 'keterangan' => 'note',
            default => $key,
        };
    }

    private function isEmptyDataRow(array $rowData): bool
    {
        foreach (['employee_code', 'employee_id', 'employee_name', 'schedule_date', 'schedule_type', 'shift', 'work_shift_id', 'note'] as $key) {
            if (trim((string) ($rowData[$key] ?? '')) !== '') {
                return false;
            }
        }

        return true;
    }

    private function normalizeDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if ($value instanceof \DateTimeInterface) {
                return $value->format('Y-m-d');
            }

            if (is_numeric($value)) {
                return Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
            }

            return Carbon::parse(trim((string) $value))->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeScheduleType(mixed $value): ?string
    {
        $normalized = mb_strtolower(trim((string) $value));
        $normalized = preg_replace('/[^\p{L}\p{N}]+/u', '_', $normalized);
        $normalized = trim((string) $normalized, '_');

        return match ($normalized) {
            'work', 'masuk', 'kerja', 'jadwal_masuk' => EmployeeSchedule::TYPE_WORK,
            'day_off', 'off', 'libur', 'libur_mingguan' => EmployeeSchedule::TYPE_DAY_OFF,
            'holiday', 'libur_perusahaan', 'libur_nasional' => EmployeeSchedule::TYPE_HOLIDAY,
            'leave', 'cuti', 'izin', 'cuti_izin' => EmployeeSchedule::TYPE_LEAVE,
            default => null,
        };
    }
}
