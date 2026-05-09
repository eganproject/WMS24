<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class EmployeesImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    /** @var array<int,array<string,mixed>> */
    public array $rows = [];

    /** @var array<int,string> */
    private array $requiredHeaders = [
        'employee_code',
        'name',
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
                'file' => 'Header wajib: employee_code, name. Header opsional: phone, employment_status, position, position_id, area, area_id, user_email, user_id, join_date. '
                    .($detected !== '' ? 'Header terdeteksi: '.$detected : ''),
            ]);
        }

        $errors = [];
        $seenCodes = [];
        $seenUserRefs = [];
        $rowIndex = 1;

        foreach ($rows as $row) {
            $rowIndex++;
            $rowData = $this->normalizeRow($row);
            $employeeCode = trim((string) ($rowData['employee_code'] ?? ''));
            $name = trim((string) ($rowData['name'] ?? ''));
            $phone = trim((string) ($rowData['phone'] ?? ''));
            $employmentStatus = strtolower(trim((string) ($rowData['employment_status'] ?? 'active')));
            $position = trim((string) ($rowData['position'] ?? ''));
            $positionRaw = trim((string) ($rowData['position_id'] ?? $rowData['position_code'] ?? $rowData['position_name'] ?? $position));
            $areaRaw = trim((string) ($rowData['area_id'] ?? $rowData['area_code'] ?? $rowData['area'] ?? ''));
            $userRaw = trim((string) ($rowData['user_id'] ?? $rowData['user_email'] ?? $rowData['email'] ?? ''));
            $joinDate = $rowData['join_date'] ?? null;

            if ($employeeCode === '' || $name === '') {
                $errors[] = "Baris {$rowIndex}: Kode karyawan dan nama wajib diisi";
                continue;
            }

            $codeKey = strtolower($employeeCode);
            if (isset($seenCodes[$codeKey])) {
                $errors[] = "Baris {$rowIndex}: Kode karyawan duplikat di file ({$employeeCode})";
                continue;
            }
            $seenCodes[$codeKey] = true;

            if (!in_array($employmentStatus, ['active', 'inactive'], true)) {
                $errors[] = "Baris {$rowIndex}: employment_status harus active atau inactive";
                continue;
            }

            if ($userRaw !== '') {
                $userKey = strtolower($userRaw);
                if (isset($seenUserRefs[$userKey])) {
                    $errors[] = "Baris {$rowIndex}: User duplikat di file ({$userRaw})";
                    continue;
                }
                $seenUserRefs[$userKey] = true;
            }

            $this->rows[] = [
                'row' => $rowIndex,
                'employee_code' => $employeeCode,
                'name' => $name,
                'phone' => $phone !== '' ? $phone : null,
                'employment_status' => $employmentStatus,
                'position' => $position !== '' ? $position : null,
                'position_raw' => $positionRaw,
                'area_raw' => $areaRaw,
                'user_raw' => $userRaw,
                'join_date' => $joinDate,
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
        $key = trim($key);
        if ($key === '') {
            return '';
        }

        $key = mb_strtolower($key);
        $key = preg_replace('/[^\p{L}\p{N}]+/u', '_', $key);
        $key = trim($key, '_');

        return match ($key) {
            'kode_karyawan', 'kode', 'nik', 'employee_id' => 'employee_code',
            'nama', 'nama_karyawan', 'employee_name' => 'name',
            'telepon', 'telp', 'no_hp', 'nomor_hp', 'hp' => 'phone',
            'status', 'status_kerja' => 'employment_status',
            'jabatan' => 'position',
            'id_jabatan' => 'position_id',
            'kode_jabatan' => 'position_code',
            'nama_jabatan' => 'position_name',
            'area', 'area_kerja', 'lane' => 'area',
            'id_area', 'lane_id' => 'area_id',
            'kode_area', 'area_code', 'lane_code', 'kode_lane' => 'area_code',
            'user', 'email_user', 'user_login' => 'user_email',
            'tanggal_masuk', 'tgl_masuk', 'join_date', 'tanggal_join' => 'join_date',
            default => $key,
        };
    }
}
