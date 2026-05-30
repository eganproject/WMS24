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
                'file' => 'Header wajib: name. Header opsional: employee_code, phone, employment_status, position, position_id, area, area_id, user_email, user_id, join_date. '
                    .'Untuk buat user baru opsional: create_user, user_password, user_roles. '
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
            $employmentStatus = $this->normalizeStatus($rowData['employment_status'] ?? 'active');
            $position = trim((string) ($rowData['position'] ?? ''));
            $positionRaw = trim((string) ($rowData['position_id'] ?? $rowData['position_code'] ?? $rowData['position_name'] ?? $position));
            $areaRaw = trim((string) ($rowData['area_id'] ?? $rowData['area_code'] ?? $rowData['area'] ?? ''));
            $userRaw = trim((string) ($rowData['user_id'] ?? $rowData['user_email'] ?? $rowData['email'] ?? ''));
            $createUser = $this->normalizeBoolean($rowData['create_user'] ?? $rowData['buat_user'] ?? false);
            $userPassword = (string) ($rowData['user_password'] ?? $rowData['password'] ?? '');
            $userRoles = trim((string) ($rowData['user_roles'] ?? $rowData['roles'] ?? $rowData['role'] ?? ''));
            $joinDate = $rowData['join_date'] ?? null;

            if ($name === '') {
                $errors[] = "Baris {$rowIndex}: Nama wajib diisi";
                continue;
            }

            $codeKey = strtolower($employeeCode);
            if ($codeKey !== '' && isset($seenCodes[$codeKey])) {
                $errors[] = "Baris {$rowIndex}: Kode karyawan duplikat di file ({$employeeCode})";
                continue;
            }
            if ($codeKey !== '') {
                $seenCodes[$codeKey] = true;
            }

            if (!in_array($employmentStatus, ['active', 'inactive'], true)) {
                $errors[] = "Baris {$rowIndex}: employment_status harus active/aktif atau inactive/nonaktif";
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

            if ($createUser && $userRaw === '') {
                $errors[] = "Baris {$rowIndex}: user_email wajib diisi jika create_user bernilai yes";
                continue;
            }

            if ($createUser && !filter_var($userRaw, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Baris {$rowIndex}: create_user hanya mendukung user_email yang valid";
                continue;
            }

            if ($createUser && trim($userPassword) !== '' && mb_strlen($userPassword) < 6) {
                $errors[] = "Baris {$rowIndex}: user_password minimal 6 karakter";
                continue;
            }

            if ($createUser && trim($userPassword) === '') {
                $errors[] = "Baris {$rowIndex}: user_password wajib diisi untuk membuat user baru";
                continue;
            }

            if ($createUser && $userRoles === '') {
                $errors[] = "Baris {$rowIndex}: user_roles wajib diisi untuk membuat user baru";
                continue;
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
                'create_user' => $createUser,
                'user_password' => $userPassword,
                'user_roles_raw' => $userRoles,
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
            'buat_user', 'create_login', 'buat_login' => 'create_user',
            'password_user', 'password_login' => 'user_password',
            'role_user', 'roles_user', 'role_login', 'roles_login' => 'user_roles',
            'tanggal_masuk', 'tgl_masuk', 'join_date', 'tanggal_join' => 'join_date',
            default => $key,
        };
    }

    private function normalizeStatus(mixed $value): string
    {
        $status = mb_strtolower(trim((string) $value));
        $status = preg_replace('/[^\p{L}\p{N}]+/u', '_', $status);
        $status = trim((string) $status, '_');

        return match ($status) {
            '', 'active', 'aktif', '1', 'ya', 'yes' => 'active',
            'inactive', 'nonaktif', 'non_aktif', 'tidak_aktif', '0', 'tidak', 'no' => 'inactive',
            default => $status,
        };
    }

    private function normalizeBoolean(mixed $value): bool
    {
        $normalized = mb_strtolower(trim((string) $value));
        $normalized = preg_replace('/[^\p{L}\p{N}]+/u', '_', $normalized);
        $normalized = trim((string) $normalized, '_');

        return in_array($normalized, ['1', 'yes', 'ya', 'y', 'true', 'buat', 'create'], true);
    }
}
