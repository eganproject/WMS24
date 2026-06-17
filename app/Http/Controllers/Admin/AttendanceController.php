<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Exports\AbsentEmployeesExport;
use App\Exports\EmployeesExport;
use App\Exports\EmployeesTemplateExport;
use App\Exports\WeeklyScheduleTemplatesExport;
use App\Exports\WorkShiftsExport;
use App\Imports\EmployeesImport;
use App\Imports\WeeklyScheduleTemplatesImport;
use App\Imports\WorkShiftsImport;
use App\Models\ActivityLog;
use App\Models\Area;
use App\Models\Attendance;
use App\Models\AttendanceDevice;
use App\Models\AttendanceRawLog;
use App\Models\AttendanceWebhookLog;
use App\Models\Employee;
use App\Models\EmployeeFingerprint;
use App\Models\EmployeeLeave;
use App\Models\EmployeePosition;
use App\Models\EmployeeSchedule;
use App\Models\EmployeeScheduleAssignment;
use App\Models\Holiday;
use App\Models\Role;
use App\Models\User;
use App\Models\WeeklyScheduleTemplate;
use App\Models\WeeklyScheduleTemplateDay;
use App\Models\WorkShift;
use App\Support\AttendanceLateNotifier;
use App\Support\AttendanceProcessor;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class AttendanceController extends Controller
{
    public function employeeSchedule()
    {
        return view('admin.attendance.employee-schedule', [
            'employees' => Employee::query()->active()->orderBy('name')->get(['id', 'employee_code', 'name']),
        ]);
    }

    public function index()
    {
        return redirect()->route('admin.attendance.employees.index');
    }

    public function section(string $section)
    {
        $templates = WeeklyScheduleTemplate::query()
            ->with(['days.shift:id,name'])
            ->orderBy('name')
            ->get(['id', 'name', 'is_active']);

        return view('admin.attendance.index', [
            'activeSection' => $section,
            'sectionLinks' => $this->sectionLinks(),
            'areas' => Area::query()->orderBy('code')->get(['id', 'code', 'name']),
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'email']),
            'positions' => EmployeePosition::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'employees' => Employee::query()->active()->orderBy('name')->get(['id', 'employee_code', 'name']),
            'recapEmployees' => Employee::query()
                ->orderBy('name')
                ->get(['id', 'employee_code', 'name', 'employment_status']),
            'devices' => AttendanceDevice::query()->orderBy('name')->get(['id', 'name']),
            'shifts' => WorkShift::query()->orderBy('name')->get(['id', 'name', 'start_time', 'end_time']),
            'templates' => $templates,
            'templateOptions' => $templates->map(fn ($template) => [
                'id' => $template->id,
                'name' => $template->name,
                'is_active' => $template->is_active,
                'days' => $template->days->sortBy('day_of_week')->map(fn ($day) => [
                    'day_of_week' => $day->day_of_week,
                    'schedule_type' => $day->schedule_type,
                    'shift' => $day->shift?->name,
                    'work_shift_id' => $day->work_shift_id,
                ])->values(),
            ])->values(),
            'nextEmployeeCode' => $this->generateEmployeeCode(),
        ]);
    }

    public function sectionPage(Request $request)
    {
        return $this->section((string) $request->route('section'));
    }

    private function sectionLinks(): array
    {
        return [
            'employees' => ['label' => 'Karyawan', 'route' => 'admin.attendance.employees.index', 'icon' => 'fas fa-users'],
            'devices' => ['label' => 'Device', 'route' => 'admin.attendance.devices.index', 'icon' => 'fas fa-fingerprint'],
            'fingerprints' => ['label' => 'Fingerprint', 'route' => 'admin.attendance.fingerprints.index', 'icon' => 'fas fa-id-badge'],
            'shifts' => ['label' => 'Shift', 'route' => 'admin.attendance.shifts.index', 'icon' => 'fas fa-clock'],
            'schedules' => ['label' => 'Jadwal', 'route' => 'admin.attendance.schedules.index', 'icon' => 'fas fa-calendar-alt'],
            'holidays' => ['label' => 'Libur', 'route' => 'admin.attendance.holidays.index', 'icon' => 'fas fa-calendar-day'],
            'templates' => ['label' => 'Template', 'route' => 'admin.attendance.templates.index', 'icon' => 'fas fa-calendar-week'],
            'leaves' => ['label' => 'Cuti/Izin', 'route' => 'admin.attendance.leaves.index', 'icon' => 'fas fa-plane-departure'],
            'raw_logs' => ['label' => 'Raw Log', 'route' => 'admin.attendance.raw-logs.index', 'icon' => 'fas fa-list'],
            'attendances' => ['label' => 'Rekap', 'route' => 'admin.attendance.attendances.index', 'icon' => 'fas fa-clipboard-check'],
            'live_display' => ['label' => 'Live Display', 'route' => 'admin.attendance.live-display.index', 'icon' => 'fas fa-tv'],
            'absences' => ['label' => 'Monitor Harian', 'route' => 'admin.attendance.absences.index', 'icon' => 'fas fa-user-check'],
            'machine_logs' => ['label' => 'Machine Log', 'route' => 'admin.attendance.machine-logs.index', 'icon' => 'fas fa-satellite-dish'],
        ];
    }

    public function liveDisplay()
    {
        return view('admin.attendance.live-display', [
            'sectionLinks' => $this->sectionLinks(),
            'feedUrl' => route('admin.attendance.live-display.feed'),
            'today' => now()->toDateString(),
        ]);
    }

    public function liveDisplayFeed(Request $request)
    {
        $date = $this->parseOptionalDate($request->input('date')) ?: now()->toDateString();
        $latestId = (int) $request->input('latest_id', 0);

        $logs = AttendanceRawLog::query()
            ->with(['employee:id,employee_code,name,position,position_id', 'employee.positionRelation:id,name', 'device:id,name,location'])
            ->whereDate('scan_at', $date)
            ->where(function ($query) {
                $query->whereNull('employee_id')
                    ->orWhereHas('employee', fn ($employeeQuery) => $employeeQuery->active());
            })
            ->latest('scan_at')
            ->latest('id')
            ->limit(12)
            ->get();

        $newLogs = $latestId > 0
            ? AttendanceRawLog::query()
                ->with(['employee:id,employee_code,name,position,position_id', 'employee.positionRelation:id,name', 'device:id,name,location'])
                ->whereDate('scan_at', $date)
                ->where(function ($query) {
                    $query->whereNull('employee_id')
                        ->orWhereHas('employee', fn ($employeeQuery) => $employeeQuery->active());
                })
                ->where('id', '>', $latestId)
                ->orderBy('scan_at')
                ->orderBy('id')
                ->get()
            : collect();

        $attendanceBase = Attendance::query()
            ->whereDate('attendance_date', $date)
            ->whereHas('employee', fn ($query) => $query->active());

        return response()->json([
            'server_time' => now()->format('Y-m-d H:i:s'),
            'date' => $date,
            'latest_id' => (int) ($logs->max('id') ?? $latestId),
            'summary' => [
                'checked_in' => (clone $attendanceBase)->whereNotNull('check_in_at')->count(),
                'checked_out' => (clone $attendanceBase)->whereNotNull('check_out_at')->count(),
                'incomplete' => (clone $attendanceBase)->whereNotNull('check_in_at')->whereNull('check_out_at')->count(),
                'late' => (clone $attendanceBase)->where('late_minutes', '>', 0)->count(),
            ],
            'latest' => $logs->first() ? $this->serializeLiveAttendanceLog($logs->first()) : null,
            'recent' => $logs->map(fn (AttendanceRawLog $log) => $this->serializeLiveAttendanceLog($log))->values(),
            'new_events' => $newLogs->map(fn (AttendanceRawLog $log) => $this->serializeLiveAttendanceLog($log))->values(),
        ]);
    }

    private function serializeLiveAttendanceLog(AttendanceRawLog $log): array
    {
        $state = strtolower((string) ($log->state ?? ''));
        $isCheckOut = in_array($state, ['check_out', 'break_out', 'overtime_out', '1'], true);
        $isCheckIn = in_array($state, ['check_in', 'break_in', 'overtime_in', '0'], true);
        $eventType = $isCheckOut ? 'out' : 'in';
        $employee = $log->employee;
        $position = $employee?->positionRelation?->name ?: $employee?->position;

        return [
            'id' => $log->id,
            'event_type' => $eventType,
            'event_label' => $isCheckOut ? 'Check-out' : ($isCheckIn ? 'Check-in' : 'Scan Absensi'),
            'greeting' => $isCheckOut ? 'Terima kasih, sampai jumpa!' : 'Selamat datang, semangat bekerja!',
            'employee_name' => $employee?->name ?? 'Karyawan belum terhubung',
            'employee_code' => $employee?->employee_code ?? $log->device_user_id,
            'position' => $position ?: '-',
            'device' => $log->device?->name ?? '-',
            'location' => $log->device?->location ?? '-',
            'scan_date' => $log->scan_at?->format('Y-m-d') ?? '-',
            'scan_time' => $log->scan_at?->format('H:i:s') ?? '-',
            'state' => $log->state ?? '-',
            'verify_type' => $log->verify_type ?? '-',
        ];
    }

    public function absencesIndex()
    {
        return view('admin.attendance.absences', [
            'sectionLinks' => $this->sectionLinks(),
            'employees' => Employee::query()
                ->active()
                ->orderBy('name')
                ->get(['id', 'employee_code', 'name']),
            'today' => now()->toDateString(),
        ]);
    }

    public function employeesData(Request $request)
    {
        $query = Employee::query()->with(['user:id,name,email', 'area:id,code,name', 'positionRelation:id,name'])->orderBy('name');
        $this->applySearch($query, $request, ['employee_code', 'name', 'phone']);

        return $this->datatable($query, $request, fn (Employee $employee) => [
            'id' => $employee->id,
            'employee_code' => $employee->employee_code,
            'name' => $employee->name,
            'phone' => $employee->phone,
            'position' => $employee->positionRelation?->name ?? $employee->position,
            'position_id' => $employee->position_id,
            'join_date' => $employee->join_date?->format('Y-m-d'),
            'employment_status' => $employee->employment_status,
            'area' => $employee->area ? "{$employee->area->code} - {$employee->area->name}" : '-',
            'user' => $employee->user ? "{$employee->user->name} ({$employee->user->email})" : '-',
            'user_id' => $employee->user_id,
            'area_id' => $employee->area_id,
        ]);
    }

    public function positionsData(Request $request)
    {
        $query = EmployeePosition::query()->orderBy('name');
        $this->applySearch($query, $request, ['name', 'description']);

        return $this->datatable($query, $request, fn (EmployeePosition $position) => [
            'id' => $position->id,
            'name' => $position->name,
            'description' => $position->description,
            'is_active' => $position->is_active,
        ]);
    }

    public function storePosition(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:employee_positions,name'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $validated['is_active'] = $request->boolean('is_active', true);
        $position = EmployeePosition::create($validated);

        return response()->json(['message' => 'Jabatan berhasil dibuat', 'position' => $position]);
    }

    public function updatePosition(Request $request, EmployeePosition $position)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('employee_positions', 'name')->ignore($position->id)],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $validated['is_active'] = $request->boolean('is_active', true);
        $position->update($validated);

        return response()->json(['message' => 'Jabatan berhasil diperbarui', 'position' => $position]);
    }

    public function destroyPosition(EmployeePosition $position)
    {
        if ($position->employees()->exists()) {
            return response()->json(['message' => 'Jabatan sudah digunakan karyawan dan tidak bisa dihapus. Nonaktifkan jabatan jika tidak dipakai.'], 422);
        }

        $position->delete();

        return response()->json(['message' => 'Jabatan berhasil dihapus']);
    }

    public function storeEmployee(Request $request)
    {
        $validated = $this->validateEmployee($request);
        $validated['employee_code'] = $this->generateEmployeeCode();
        $employee = Employee::create($validated);

        return response()->json(['message' => 'Karyawan berhasil dibuat', 'employee' => $employee]);
    }

    public function importEmployees(Request $request)
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls'],
            'mode' => ['nullable', Rule::in(['create_only', 'upsert'])],
        ]);

        $mode = $validated['mode'] ?? 'create_only';
        $import = new EmployeesImport();
        Excel::import($import, $validated['file']);

        $rows = $import->rows;
        if (empty($rows)) {
            throw ValidationException::withMessages([
                'file' => 'Tidak ada data valid untuk diimport',
            ]);
        }

        $areas = Area::query()->get(['id', 'code', 'name']);
        $areaById = $areas->keyBy(fn ($area) => (string) $area->id);
        $areaByCode = $areas->keyBy(fn ($area) => strtolower((string) $area->code));
        $areaByName = $areas->keyBy(fn ($area) => strtolower((string) $area->name));

        $positions = EmployeePosition::query()->get(['id', 'name']);
        $positionById = $positions->keyBy(fn ($position) => (string) $position->id);
        $positionByName = $positions->keyBy(fn ($position) => strtolower((string) $position->name));

        $users = User::query()->get(['id', 'name', 'email']);
        $userById = $users->keyBy(fn ($user) => (string) $user->id);
        $userByEmail = $users->keyBy(fn ($user) => strtolower((string) $user->email));

        $roles = Role::query()->get(['id', 'name', 'slug']);
        $roleById = $roles->keyBy(fn ($role) => (string) $role->id);
        $roleBySlug = $roles->keyBy(fn ($role) => strtolower((string) $role->slug));
        $roleByName = $roles->keyBy(fn ($role) => strtolower((string) $role->name));

        $errors = [];
        $prepared = [];
        $reservedCodes = [];

        foreach ($rows as $row) {
            $rowNo = $row['row'] ?? '?';
            $employeeCode = trim((string) ($row['employee_code'] ?? ''));
            $existingEmployee = $employeeCode !== ''
                ? Employee::query()->where('employee_code', $employeeCode)->first()
                : null;

            if ($existingEmployee && $mode === 'create_only') {
                $errors[] = "Baris {$rowNo}: Kode karyawan sudah ada ({$employeeCode})";
                continue;
            }

            if ($employeeCode === '') {
                $employeeCode = $this->generateEmployeeCode($reservedCodes);
            } else {
                $reservedCodes[strtolower($employeeCode)] = true;
            }

            $areaId = null;
            $areaRaw = trim((string) ($row['area_raw'] ?? ''));
            if ($areaRaw !== '') {
                $area = is_numeric($areaRaw)
                    ? $areaById->get((string) $areaRaw)
                    : ($areaByCode->get(strtolower($areaRaw)) ?? $areaByName->get(strtolower($areaRaw)));

                if (!$area) {
                    $errors[] = "Baris {$rowNo}: Area tidak ditemukan ({$areaRaw})";
                    continue;
                }
                $areaId = $area->id;
            }

            $positionId = null;
            $positionRaw = trim((string) ($row['position_raw'] ?? ''));
            if ($positionRaw !== '') {
                $position = is_numeric($positionRaw)
                    ? $positionById->get((string) $positionRaw)
                    : $positionByName->get(strtolower($positionRaw));

                if ($position) {
                    $positionId = $position->id;
                }
            }

            $userId = null;
            $userPayload = null;
            $userRaw = trim((string) ($row['user_raw'] ?? ''));
            $createUser = (bool) ($row['create_user'] ?? false);
            if ($userRaw !== '') {
                $user = is_numeric($userRaw)
                    ? $userById->get((string) $userRaw)
                    : $userByEmail->get(strtolower($userRaw));

                if (!$user && !$createUser) {
                    $errors[] = "Baris {$rowNo}: User login tidak ditemukan ({$userRaw})";
                    continue;
                }

                if ($user) {
                    if ($createUser) {
                        $errors[] = "Baris {$rowNo}: Email user sudah terdaftar ({$userRaw}). Gunakan create_user kosong/no jika ingin menghubungkan user yang sudah ada.";
                        continue;
                    }

                    $usedBy = Employee::query()
                        ->where('user_id', $user->id)
                        ->when($existingEmployee, fn ($query) => $query->where('id', '!=', $existingEmployee->id))
                        ->first();

                    if ($usedBy) {
                        $errors[] = "Baris {$rowNo}: User login sudah dipakai karyawan lain ({$userRaw})";
                        continue;
                    }
                    $userId = $user->id;
                } else {
                    $roleIds = [];
                    $tokens = preg_split('/[;,|]+/', (string) ($row['user_roles_raw'] ?? '')) ?: [];
                    foreach ($tokens as $token) {
                        $token = trim((string) $token);
                        if ($token === '') {
                            continue;
                        }

                        $role = is_numeric($token)
                            ? $roleById->get((string) $token)
                            : ($roleBySlug->get(strtolower($token)) ?? $roleByName->get(strtolower($token)));

                        if (!$role) {
                            $errors[] = "Baris {$rowNo}: Role user tidak ditemukan ({$token})";
                            continue 2;
                        }
                        $roleIds[] = $role->id;
                    }

                    $userPayload = [
                        'name' => $row['name'],
                        'email' => strtolower($userRaw),
                        'password' => (string) ($row['user_password'] ?? ''),
                        'roles' => array_values(array_unique($roleIds)),
                        'area_id' => $areaId,
                    ];
                }
            }

            $joinDate = $this->parseEmployeeImportDate($row['join_date'] ?? null, $rowNo);

            $prepared[] = [
                'existing' => $existingEmployee,
                'user_payload' => $userPayload,
                'payload' => [
                    'employee_code' => $employeeCode,
                    'name' => $row['name'],
                    'phone' => $row['phone'],
                    'employment_status' => $row['employment_status'],
                    'position' => $row['position'],
                    'position_id' => $positionId,
                    'area_id' => $areaId,
                    'user_id' => $userId,
                    'join_date' => $joinDate,
                ],
            ];
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages([
                'file' => implode(' | ', array_slice($errors, 0, 8)),
            ]);
        }

        $created = 0;
        $updated = 0;
        $usersCreated = 0;

        DB::beginTransaction();
        try {
            foreach ($prepared as $row) {
                if ($row['user_payload']) {
                    $newUser = User::create([
                        'name' => $row['user_payload']['name'],
                        'email' => $row['user_payload']['email'],
                        'password' => Hash::make($row['user_payload']['password']),
                        'avatar' => User::defaultAvatar(),
                        'email_verified_at' => now(),
                        'area_id' => $row['user_payload']['area_id'],
                    ]);
                    $newUser->roles()->sync($row['user_payload']['roles']);
                    $row['payload']['user_id'] = $newUser->id;
                    $usersCreated++;
                }

                if ($row['existing']) {
                    $row['existing']->update($row['payload']);
                    $updated++;
                    continue;
                }

                Employee::create($row['payload']);
                $created++;
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Gagal import karyawan',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Import karyawan berhasil',
            'created' => $created,
            'updated' => $updated,
            'users_created' => $usersCreated,
        ]);
    }

    public function downloadEmployeesImportTemplate()
    {
        return Excel::download(
            new EmployeesTemplateExport(),
            'template_import_karyawan.xlsx'
        );
    }

    public function exportTemplates()
    {
        return Excel::download(
            new WeeklyScheduleTemplatesExport(),
            'template_jadwal.xlsx'
        );
    }

    public function exportShifts()
    {
        return Excel::download(
            new WorkShiftsExport(),
            'shift_kerja.xlsx'
        );
    }

    public function exportEmployees(Request $request)
    {
        return Excel::download(
            new EmployeesExport((string) $request->input('q', '')),
            'export_karyawan_'.now()->format('Ymd_His').'.xlsx'
        );
    }

    public function updateEmployee(Request $request, Employee $employee)
    {
        $validated = $this->validateEmployee($request, $employee);
        $validated['employee_code'] = $employee->employee_code;
        $employee->update($validated);

        return response()->json(['message' => 'Karyawan berhasil diperbarui', 'employee' => $employee]);
    }

    public function destroyEmployee(Employee $employee)
    {
        if ($employee->attendances()->exists() || $employee->fingerprints()->exists()) {
            return response()->json(['message' => 'Karyawan sudah memiliki data absensi/fingerprint dan tidak bisa dihapus. Nonaktifkan statusnya jika sudah tidak bekerja.'], 422);
        }

        $employee->delete();

        return response()->json(['message' => 'Karyawan berhasil dihapus']);
    }

    public function devicesData(Request $request)
    {
        $query = AttendanceDevice::query()->orderBy('name');
        $this->applySearch($query, $request, ['name', 'serial_number', 'ip_address', 'location', 'device_type']);

        return $this->datatable($query, $request, fn (AttendanceDevice $device) => [
            'id' => $device->id,
            'name' => $device->name,
            'serial_number' => $device->serial_number,
            'ip_address' => $device->ip_address,
            'port' => $device->port,
            'location' => $device->location,
            'device_type' => $device->device_type,
            'is_active' => $device->is_active,
            'last_synced_at' => $device->last_synced_at?->format('Y-m-d H:i:s'),
        ]);
    }

    public function storeDevice(Request $request)
    {
        $validated = $this->validateDevice($request);
        $device = AttendanceDevice::create($validated);

        return response()->json(['message' => 'Device absensi berhasil dibuat', 'device' => $device]);
    }

    public function updateDevice(Request $request, AttendanceDevice $device)
    {
        $validated = $this->validateDevice($request, $device);
        $device->update($validated);

        return response()->json(['message' => 'Device absensi berhasil diperbarui', 'device' => $device]);
    }

    public function destroyDevice(AttendanceDevice $device)
    {
        if ($device->rawLogs()->exists()) {
            return response()->json(['message' => 'Device sudah memiliki raw log dan tidak bisa dihapus. Nonaktifkan device jika tidak dipakai.'], 422);
        }

        $device->delete();

        return response()->json(['message' => 'Device absensi berhasil dihapus']);
    }

    public function fingerprintsData(Request $request)
    {
        $query = EmployeeFingerprint::query()
            ->with(['employee:id,employee_code,name', 'device:id,name'])
            ->latest('id');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('device_user_id', 'like', "%{$search}%")
                    ->orWhere('fingerprint_uid', 'like', "%{$search}%")
                    ->orWhereHas('employee', fn ($employeeQuery) => $employeeQuery
                        ->where('employee_code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%"))
                    ->orWhereHas('device', fn ($deviceQuery) => $deviceQuery
                        ->where('name', 'like', "%{$search}%"));

                if (str_contains(strtolower($search), 'semua')) {
                    $q->orWhereNull('attendance_device_id');
                }
            });
        }

        return $this->datatable($query, $request, fn (EmployeeFingerprint $fingerprint) => [
            'id' => $fingerprint->id,
            'employee_id' => $fingerprint->employee_id,
            'attendance_device_id' => $fingerprint->attendance_device_id,
            'employee' => $fingerprint->employee ? "{$fingerprint->employee->employee_code} - {$fingerprint->employee->name}" : '-',
            'device' => $fingerprint->device?->name ?? 'Semua device',
            'device_user_id' => $fingerprint->device_user_id,
            'fingerprint_uid' => $fingerprint->fingerprint_uid,
            'is_active' => $fingerprint->is_active,
            'enrolled_at' => $fingerprint->enrolled_at?->format('Y-m-d H:i:s'),
        ]);
    }

    public function storeFingerprint(Request $request)
    {
        $validated = $this->validateFingerprint($request);
        $fingerprint = EmployeeFingerprint::create($validated);

        return response()->json(['message' => 'Mapping fingerprint berhasil dibuat', 'fingerprint' => $fingerprint]);
    }

    public function updateFingerprint(Request $request, EmployeeFingerprint $fingerprint)
    {
        $validated = $this->validateFingerprint($request, $fingerprint);
        $fingerprint->update($validated);

        return response()->json(['message' => 'Mapping fingerprint berhasil diperbarui', 'fingerprint' => $fingerprint]);
    }

    public function destroyFingerprint(EmployeeFingerprint $fingerprint)
    {
        $fingerprint->delete();

        return response()->json(['message' => 'Mapping fingerprint berhasil dihapus']);
    }

    public function shiftsData(Request $request)
    {
        $query = WorkShift::query()->orderBy('name');
        $this->applySearch($query, $request, ['name']);

        return $this->datatable($query, $request, fn (WorkShift $shift) => [
            'id' => $shift->id,
            'name' => $shift->name,
            'start_time' => $this->timeValue($shift->start_time),
            'end_time' => $this->timeValue($shift->end_time),
            'break_start_time' => $this->timeValue($shift->break_start_time),
            'break_end_time' => $this->timeValue($shift->break_end_time),
            'late_tolerance_minutes' => $shift->late_tolerance_minutes,
            'checkout_tolerance_minutes' => $shift->checkout_tolerance_minutes,
            'overtime_start_after_minutes' => $shift->overtime_start_after_minutes,
            'minimum_overtime_minutes' => $shift->minimum_overtime_minutes,
            'crosses_midnight' => $shift->crosses_midnight,
            'is_active' => $shift->is_active,
        ]);
    }

    public function storeShift(Request $request)
    {
        $shift = WorkShift::create($this->validateShift($request));

        return response()->json(['message' => 'Shift berhasil dibuat', 'shift' => $shift]);
    }

    public function importShifts(Request $request)
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls'],
        ]);

        $import = new WorkShiftsImport();
        Excel::import($import, $validated['file']);

        $rows = $import->rows;
        if (empty($rows)) {
            throw ValidationException::withMessages([
                'file' => 'Tidak ada data valid untuk diimport',
            ]);
        }

        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($rows, &$created, &$updated) {
            foreach ($rows as $row) {
                $shift = WorkShift::query()
                    ->where('name', $row['name'])
                    ->first();

                $payload = [
                    'name' => $row['name'],
                    'start_time' => $row['start_time'],
                    'end_time' => $row['end_time'],
                    'break_start_time' => $row['break_start_time'],
                    'break_end_time' => $row['break_end_time'],
                    'late_tolerance_minutes' => $row['late_tolerance_minutes'],
                    'checkout_tolerance_minutes' => $row['checkout_tolerance_minutes'],
                    'overtime_start_after_minutes' => $row['overtime_start_after_minutes'],
                    'minimum_overtime_minutes' => $row['minimum_overtime_minutes'],
                    'crosses_midnight' => $row['crosses_midnight'],
                    'is_active' => $row['is_active'],
                ];

                if ($shift) {
                    $shift->update($payload);
                    $updated++;
                } else {
                    WorkShift::create($payload);
                    $created++;
                }
            }
        });

        return response()->json([
            'message' => 'Import shift berhasil',
            'created' => $created,
            'updated' => $updated,
        ]);
    }

    public function updateShift(Request $request, WorkShift $shift)
    {
        $shift->update($this->validateShift($request));

        return response()->json(['message' => 'Shift berhasil diperbarui', 'shift' => $shift]);
    }

    public function destroyShift(WorkShift $shift)
    {
        if (EmployeeSchedule::where('work_shift_id', $shift->id)->exists() || Attendance::where('work_shift_id', $shift->id)->exists()) {
            return response()->json(['message' => 'Shift sudah digunakan jadwal/absensi dan tidak bisa dihapus. Nonaktifkan shift jika tidak dipakai.'], 422);
        }

        $shift->delete();

        return response()->json(['message' => 'Shift berhasil dihapus']);
    }

    public function schedulesData(Request $request)
    {
        $holidayCache = [];
        $query = EmployeeSchedule::query()
            ->with(['employee:id,employee_code,name', 'shift:id,name,start_time,end_time,crosses_midnight'])
            ->whereHas('employee', fn ($employeeQuery) => $employeeQuery->active())
            ->when($request->input('employee_id'), fn ($q, $id) => $q->where('employee_id', $id))
            ->when($request->input('date_from'), fn ($q, $date) => $q->whereDate('schedule_date', '>=', $date))
            ->when($request->input('date_to'), fn ($q, $date) => $q->whereDate('schedule_date', '<=', $date))
            ->when($request->input('schedule_type'), fn ($q, $type) => $q->where('schedule_type', $type))
            ->latest('schedule_date');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('schedule_date', 'like', "%{$search}%")
                    ->orWhere('schedule_type', 'like', "%{$search}%")
                    ->orWhereHas('employee', fn ($eq) => $eq
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('employee_code', 'like', "%{$search}%"));
            });
        }

        return $this->datatable($query, $request, function (EmployeeSchedule $schedule) use (&$holidayCache) {
            $dateKey = $schedule->schedule_date?->toDateString();
            $holiday = $dateKey
                ? ($holidayCache[$dateKey] ??= Holiday::query()->whereDate('holiday_date', $dateKey)->first())
                : null;
            $effectiveType = $holiday ? EmployeeSchedule::TYPE_HOLIDAY : $schedule->schedule_type;
            $effectiveShift = $effectiveType === EmployeeSchedule::TYPE_WORK ? $schedule->shift : null;

            return [
                'id' => $schedule->id,
                'employee_id' => $schedule->employee_id,
                'work_shift_id' => $schedule->work_shift_id,
                'employee_schedule_assignment_id' => $schedule->employee_schedule_assignment_id,
                'employee' => $schedule->employee ? "{$schedule->employee->employee_code} - {$schedule->employee->name}" : '-',
                'schedule_date' => $schedule->schedule_date?->format('Y-m-d'),
                'schedule_type' => $schedule->schedule_type,
                'effective_schedule_type' => $effectiveType,
                'effective_schedule_type_label' => $this->scheduleTypeLabel($effectiveType, $holiday),
                'is_effective_override' => $effectiveType !== $schedule->schedule_type,
                'shift' => $schedule->shift?->name ?? '-',
                'effective_shift' => $effectiveShift?->name ?? '-',
                'shift_start_time' => $this->timeValue($effectiveShift?->start_time),
                'shift_end_time' => $this->timeValue($effectiveShift?->end_time),
                'crosses_midnight' => $effectiveShift?->crosses_midnight ?? false,
                'note' => $schedule->note,
                'effective_note' => $holiday?->name ?: $schedule->note,
                'is_editable' => $schedule->schedule_date?->greaterThanOrEqualTo(today()) ?? false,
            ];
        });
    }

    public function calendarEvents(Request $request)
    {
        $start = Carbon::parse($request->input('start', now()->startOfMonth()->toDateString()))->toDateString();
        $end = Carbon::parse($request->input('end', now()->endOfMonth()->toDateString()))->toDateString();
        $employeeId = $request->integer('employee_id') ?: null;
        $eventsByDate = [];
        $holidays = Holiday::query()
            ->whereDate('holiday_date', '>=', $start)
            ->whereDate('holiday_date', '<=', $end)
            ->get();
        $holidayDates = $holidays
            ->map(fn (Holiday $holiday) => $holiday->holiday_date?->toDateString())
            ->filter()
            ->flip();
        $leaveDatesByEmployee = [];
        $approvedLeaves = EmployeeLeave::query()
            ->with('employee:id,employee_code,name')
            ->where('status', EmployeeLeave::STATUS_APPROVED)
            ->when($employeeId, fn ($query) => $query->where('employee_id', $employeeId))
            ->whereDate('start_date', '<=', $end)
            ->whereDate('end_date', '>=', $start)
            ->get();

        $approvedLeaves->each(function (EmployeeLeave $leave) use (&$leaveDatesByEmployee, $start, $end) {
            $current = $leave->start_date->copy()->max(Carbon::parse($start));
            $until = $leave->end_date->copy()->min(Carbon::parse($end));

            while ($current->lte($until)) {
                $dateKey = $current->toDateString();
                $leaveDatesByEmployee[$leave->employee_id][$dateKey] = true;
                $current->addDay();
            }
        });

        EmployeeSchedule::query()
            ->with(['employee:id,employee_code,name', 'shift:id,name'])
            ->whereHas('employee', fn ($query) => $query->active())
            ->when($employeeId, fn ($query) => $query->where('employee_id', $employeeId))
            ->whereDate('schedule_date', '>=', $start)
            ->whereDate('schedule_date', '<=', $end)
            ->get()
            ->each(function (EmployeeSchedule $schedule) use (&$eventsByDate, $holidayDates, $leaveDatesByEmployee) {
                $date = $schedule->schedule_date?->toDateString();
                if (
                    $schedule->schedule_type === EmployeeSchedule::TYPE_WORK
                    && ($holidayDates->has($date) || !empty($leaveDatesByEmployee[$schedule->employee_id][$date]))
                ) {
                    return;
                }

                $label = match ($schedule->schedule_type) {
                    EmployeeSchedule::TYPE_WORK => 'Masuk',
                    EmployeeSchedule::TYPE_DAY_OFF => 'Libur',
                    EmployeeSchedule::TYPE_HOLIDAY => 'Libur Perusahaan',
                    EmployeeSchedule::TYPE_LEAVE => 'Cuti/Izin',
                    default => $schedule->schedule_type,
                };

                $this->addCalendarSummary($eventsByDate, $date, 'schedule_'.$schedule->schedule_type, [
                    'label' => 'Jadwal '.$label,
                    'color' => match ($schedule->schedule_type) {
                        EmployeeSchedule::TYPE_WORK => '#009ef7',
                        EmployeeSchedule::TYPE_DAY_OFF => '#7e8299',
                        EmployeeSchedule::TYPE_HOLIDAY => '#f1416c',
                        EmployeeSchedule::TYPE_LEAVE => '#ffc700',
                        default => '#7239ea',
                    },
                    'textColor' => in_array($schedule->schedule_type, [EmployeeSchedule::TYPE_LEAVE], true) ? '#181c32' : '#ffffff',
                    'detail' => trim(implode(' ', array_filter([
                        $schedule->employee ? "{$schedule->employee->employee_code} - {$schedule->employee->name}" : null,
                        $schedule->shift ? "({$schedule->shift->name})" : null,
                        $schedule->note ? "- {$schedule->note}" : null,
                    ]))),
                ]);
            });

        $holidays->each(function (Holiday $holiday) use (&$eventsByDate) {
            $date = $holiday->holiday_date?->toDateString();
            $label = $this->scheduleTypeLabel(EmployeeSchedule::TYPE_HOLIDAY, $holiday);

            $this->addCalendarSummary($eventsByDate, $date, 'global_holiday', [
                'label' => $label,
                'color' => '#f1416c',
                'textColor' => '#ffffff',
                'detail' => $holiday->name,
            ]);
        });

        $approvedLeaves->each(function (EmployeeLeave $leave) use (&$eventsByDate, $start, $end) {
            $current = $leave->start_date->copy()->max(Carbon::parse($start));
            $until = $leave->end_date->copy()->min(Carbon::parse($end));

            while ($current->lte($until)) {
                $date = $current->toDateString();

                $this->addCalendarSummary($eventsByDate, $date, 'approved_leave', [
                    'label' => 'Cuti/Izin',
                    'color' => '#ffc700',
                    'textColor' => '#181c32',
                    'detail' => trim(implode(' ', array_filter([
                        $leave->employee ? "{$leave->employee->employee_code} - {$leave->employee->name}" : null,
                        $leave->leave_type ? "({$leave->leave_type})" : null,
                        $leave->reason ? "- {$leave->reason}" : null,
                    ]))),
                ]);

                $current->addDay();
            }
        });

        $events = collect($eventsByDate)
            ->flatMap(fn (array $groups, string $date) => collect($groups)->map(fn (array $group) => [
                'title' => $group['count'].' '.$group['label'],
                'start' => $date,
                'allDay' => true,
                'backgroundColor' => $group['color'],
                'borderColor' => $group['color'],
                'textColor' => $group['textColor'],
                'extendedProps' => [
                    'type' => 'summary',
                    'label' => $group['label'],
                    'count' => $group['count'],
                    'details' => $group['details'],
                ],
            ])->values())
            ->values();

        return response()->json($events->values());
    }

    private function scheduleTypeLabel(string $scheduleType, ?Holiday $holiday = null): string
    {
        if ($scheduleType === EmployeeSchedule::TYPE_HOLIDAY && $holiday) {
            return $holiday->type === 'national' ? 'Libur Nasional' : 'Libur Perusahaan';
        }

        return match ($scheduleType) {
            EmployeeSchedule::TYPE_WORK => 'Masuk',
            EmployeeSchedule::TYPE_DAY_OFF => 'Libur',
            EmployeeSchedule::TYPE_HOLIDAY => 'Libur Perusahaan',
            EmployeeSchedule::TYPE_LEAVE => 'Cuti/Izin',
            default => $scheduleType,
        };
    }

    private function addCalendarSummary(array &$eventsByDate, ?string $date, string $key, array $payload): void
    {
        if (!$date) {
            return;
        }

        $eventsByDate[$date][$key] ??= [
            'label' => $payload['label'],
            'color' => $payload['color'],
            'textColor' => $payload['textColor'] ?? '#ffffff',
            'count' => 0,
            'details' => [],
        ];

        $eventsByDate[$date][$key]['count']++;

        if (!empty($payload['detail'])) {
            $eventsByDate[$date][$key]['details'][] = $payload['detail'];
        }
    }

    public function storeSchedule(Request $request)
    {
        $validated = $this->validateSchedule($request);
        $validated['created_by'] = auth()->id();
        $validated['employee_schedule_assignment_id'] = null;
        if ($validated['schedule_type'] !== EmployeeSchedule::TYPE_WORK) {
            $validated['work_shift_id'] = null;
        }
        $schedule = EmployeeSchedule::query()->updateOrCreate(
            [
                'employee_id' => $validated['employee_id'],
                'schedule_date' => $validated['schedule_date'],
            ],
            $validated
        );

        app(AttendanceProcessor::class)->rebuildDailyAttendance($schedule->employee, $schedule->schedule_date);
        $this->writeAttendanceAudit($request, 'Menyimpan jadwal karyawan', $schedule, null, $this->auditSnapshot($schedule->fresh()));

        return response()->json(['message' => 'Jadwal karyawan berhasil disimpan', 'schedule' => $schedule]);
    }

    public function updateSchedule(Request $request, EmployeeSchedule $schedule)
    {
        $this->ensureScheduleIsEditable($schedule);
        $before = $this->auditSnapshot($schedule);
        $oldEmployee = $schedule->employee;
        $oldDate = $schedule->schedule_date;
        $validated = $this->validateSchedule($request);
        $validated['employee_schedule_assignment_id'] = null;
        if ($validated['schedule_type'] !== EmployeeSchedule::TYPE_WORK) {
            $validated['work_shift_id'] = null;
        }

        $schedule->update($validated);
        $schedule->refresh();

        if ($oldEmployee) {
            app(AttendanceProcessor::class)->rebuildDailyAttendance($oldEmployee, $oldDate);
        }
        app(AttendanceProcessor::class)->rebuildDailyAttendance($schedule->employee, $schedule->schedule_date);
        $this->writeAttendanceAudit($request, 'Mengubah jadwal karyawan', $schedule, $before, $this->auditSnapshot($schedule));

        return response()->json(['message' => 'Jadwal karyawan berhasil diperbarui', 'schedule' => $schedule]);
    }

    public function destroySchedule(Request $request, EmployeeSchedule $schedule)
    {
        $this->ensureScheduleIsEditable($schedule);
        $before = $this->auditSnapshot($schedule);
        $employee = $schedule->employee;
        $date = $schedule->schedule_date;
        $schedule->delete();
        app(AttendanceProcessor::class)->rebuildDailyAttendance($employee, $date);
        $this->writeAttendanceAudit($request, 'Menghapus jadwal karyawan', $schedule, $before, null);

        return response()->json(['message' => 'Jadwal karyawan berhasil dihapus']);
    }

    public function holidaysData(Request $request)
    {
        $query = Holiday::query()->latest('holiday_date');
        $this->applySearch($query, $request, ['name', 'type']);

        return $this->datatable($query, $request, fn (Holiday $holiday) => [
            'id' => $holiday->id,
            'holiday_date' => $holiday->holiday_date?->format('Y-m-d'),
            'name' => $holiday->name,
            'type' => $holiday->type,
            'is_paid' => $holiday->is_paid,
        ]);
    }

    public function storeHoliday(Request $request)
    {
        $validated = $request->validate([
            'holiday_date' => ['required', 'date', 'unique:holidays,holiday_date'],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', 'string', 'max:30'],
            'is_paid' => ['nullable', 'boolean'],
        ]);
        $validated['is_paid'] = $request->boolean('is_paid');
        $holiday = Holiday::create($validated);
        $rebuiltCount = $this->rebuildAttendanceForHolidayDates([$holiday->holiday_date]);

        return response()->json([
            'message' => "Hari libur berhasil dibuat dan rekap {$rebuiltCount} karyawan diperbarui.",
            'holiday' => $holiday,
            'rebuilt_count' => $rebuiltCount,
        ]);
    }

    public function updateHoliday(Request $request, Holiday $holiday)
    {
        $oldDate = $holiday->holiday_date?->copy();
        $validated = $request->validate([
            'holiday_date' => ['required', 'date', Rule::unique('holidays', 'holiday_date')->ignore($holiday->id)],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', 'string', 'max:30'],
            'is_paid' => ['nullable', 'boolean'],
        ]);
        $validated['is_paid'] = $request->boolean('is_paid');
        $holiday->update($validated);
        $holiday->refresh();
        $rebuiltCount = $this->rebuildAttendanceForHolidayDates(array_filter([
            $oldDate,
            $holiday->holiday_date,
        ]));

        return response()->json([
            'message' => "Hari libur berhasil diperbarui dan rekap {$rebuiltCount} karyawan diperbarui.",
            'holiday' => $holiday,
            'rebuilt_count' => $rebuiltCount,
        ]);
    }

    public function destroyHoliday(Holiday $holiday)
    {
        $date = $holiday->holiday_date?->copy();
        $holiday->delete();
        $rebuiltCount = $this->rebuildAttendanceForHolidayDates([$date]);

        return response()->json([
            'message' => "Hari libur berhasil dihapus dan rekap {$rebuiltCount} karyawan diperbarui.",
            'rebuilt_count' => $rebuiltCount,
        ]);
    }

    public function templatesData(Request $request)
    {
        $query = WeeklyScheduleTemplate::query()->with('days.shift')->orderBy('name');

        return $this->datatable($query, $request, fn (WeeklyScheduleTemplate $template) => [
            'id' => $template->id,
            'name' => $template->name,
            'is_active' => $template->is_active,
            'days' => $template->days->sortBy('day_of_week')->map(fn ($day) => [
                'day_of_week' => $day->day_of_week,
                'schedule_type' => $day->schedule_type,
                'work_shift_id' => $day->work_shift_id,
                'shift' => $day->shift?->name,
            ])->values(),
        ]);
    }

    public function storeTemplate(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'is_active' => ['nullable', 'boolean'],
            'days' => ['nullable', 'array'],
            'days.*.day_of_week' => ['required', 'integer', 'between:1,7'],
            'days.*.schedule_type' => ['required', 'string', 'max:30'],
            'days.*.work_shift_id' => ['nullable', 'integer', 'exists:work_shifts,id'],
        ]);

        $template = DB::transaction(function () use ($validated, $request) {
            $template = WeeklyScheduleTemplate::create([
                'name' => $validated['name'],
                'is_active' => $request->boolean('is_active', true),
            ]);
            $this->syncTemplateDays($template, $validated['days'] ?? []);
            return $template;
        });

        return response()->json(['message' => 'Template jadwal berhasil dibuat', 'template' => $template]);
    }

    public function importTemplates(Request $request)
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls'],
        ]);

        $import = new WeeklyScheduleTemplatesImport();
        Excel::import($import, $validated['file']);

        $templates = $import->templates;
        if (empty($templates)) {
            throw ValidationException::withMessages([
                'file' => 'Tidak ada data valid untuk diimport',
            ]);
        }

        $shifts = WorkShift::query()->get(['id', 'name']);
        $shiftById = $shifts->keyBy(fn ($shift) => (string) $shift->id);
        $shiftByName = $shifts->keyBy(fn ($shift) => mb_strtolower((string) $shift->name));

        $created = 0;
        $updated = 0;
        $errors = [];

        DB::transaction(function () use ($templates, $shiftById, $shiftByName, &$created, &$updated, &$errors) {
            foreach ($templates as $templateData) {
                $days = [];
                foreach ($templateData['days'] as $day) {
                    $shiftId = null;
                    if (($day['schedule_type'] ?? '') === EmployeeSchedule::TYPE_WORK) {
                        $shiftRaw = trim((string) ($day['shift_raw'] ?? ''));
                        $shift = is_numeric($shiftRaw)
                            ? $shiftById->get((string) $shiftRaw)
                            : $shiftByName->get(mb_strtolower($shiftRaw));

                        if (!$shift) {
                            $errors[] = "Baris {$day['row']}: Shift tidak ditemukan ({$shiftRaw})";
                            continue;
                        }
                        $shiftId = $shift->id;
                    }

                    $days[] = [
                        'day_of_week' => $day['day_of_week'],
                        'schedule_type' => $day['schedule_type'],
                        'work_shift_id' => $shiftId,
                    ];
                }

                if (!empty($errors)) {
                    continue;
                }

                $template = WeeklyScheduleTemplate::query()
                    ->where('name', $templateData['name'])
                    ->first();

                if ($template) {
                    $template->update([
                        'is_active' => (bool) $templateData['is_active'],
                    ]);
                    $updated++;
                } else {
                    $template = WeeklyScheduleTemplate::create([
                        'name' => $templateData['name'],
                        'is_active' => (bool) $templateData['is_active'],
                    ]);
                    $created++;
                }

                $this->syncTemplateDays($template, $days);
                $this->syncGeneratedSchedulesForTemplate($template);
            }

            if (!empty($errors)) {
                throw ValidationException::withMessages([
                    'file' => implode(' | ', array_slice($errors, 0, 5)),
                ]);
            }
        });

        return response()->json([
            'message' => 'Import template jadwal berhasil',
            'created' => $created,
            'updated' => $updated,
        ]);
    }

    public function updateTemplate(Request $request, WeeklyScheduleTemplate $template)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'is_active' => ['nullable', 'boolean'],
            'days' => ['nullable', 'array'],
            'days.*.day_of_week' => ['required', 'integer', 'between:1,7'],
            'days.*.schedule_type' => ['required', 'string', 'max:30'],
            'days.*.work_shift_id' => ['nullable', 'integer', 'exists:work_shifts,id'],
        ]);

        DB::transaction(function () use ($template, $validated, $request) {
            $template->update([
                'name' => $validated['name'],
                'is_active' => $request->boolean('is_active', true),
            ]);
            $this->syncTemplateDays($template, $validated['days'] ?? []);
            $this->syncGeneratedSchedulesForTemplate($template);
        });

        return response()->json(['message' => 'Template jadwal berhasil diperbarui dan jadwal mulai hari ini sudah disinkronkan.']);
    }

    public function destroyTemplate(WeeklyScheduleTemplate $template)
    {
        if (EmployeeScheduleAssignment::where('weekly_schedule_template_id', $template->id)->exists()) {
            return response()->json(['message' => 'Template sudah pernah diterapkan ke karyawan dan tidak bisa dihapus. Nonaktifkan template jika tidak dipakai.'], 422);
        }

        $template->delete();

        return response()->json(['message' => 'Template jadwal berhasil dihapus']);
    }

    public function assignTemplate(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'integer', $this->activeEmployeeExistsRule()],
            'weekly_schedule_template_id' => ['required', 'integer', 'exists:weekly_schedule_templates,id'],
            'effective_from' => ['required', 'date', 'after_or_equal:today'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'conflict_strategy' => ['nullable', 'string', Rule::in(['overwrite', 'skip_existing'])],
        ]);

        $from = Carbon::parse($validated['effective_from'])->startOfDay();
        $until = !empty($validated['effective_until'])
            ? Carbon::parse($validated['effective_until'])->startOfDay()
            : $from->copy()->endOfMonth();
        $validated['effective_until'] = $until->toDateString();

        if ($from->diffInDays($until) > 366) {
            return response()->json([
                'message' => 'Rentang penerapan template maksimal 366 hari.',
                'errors' => ['effective_until' => ['Rentang penerapan template maksimal 366 hari.']],
            ], 422);
        }

        $conflicts = $this->detectTemplateAssignmentConflicts((int) $validated['employee_id'], $from, $until);
        if (empty($validated['conflict_strategy']) && ($conflicts['schedule_count'] > 0 || $conflicts['assignment_count'] > 0)) {
            return response()->json([
                'message' => 'Periode ini memiliki jadwal atau assignment template yang sudah ada.',
                'requires_decision' => true,
                'conflicts' => $conflicts,
            ], 409);
        }

        $strategy = $validated['conflict_strategy'] ?? 'overwrite';
        unset($validated['conflict_strategy']);

        $generatedCount = 0;
        $skippedCount = 0;
        $assignment = DB::transaction(function () use ($validated, $from, $until, $strategy, &$generatedCount, &$skippedCount) {
            $assignment = EmployeeScheduleAssignment::create($validated);
            $result = $this->materializeTemplateSchedules($assignment, $from, $until, $strategy);
            $generatedCount = $result['generated'];
            $skippedCount = $result['skipped'];

            return $assignment;
        });

        $assignment->load(['employee:id,employee_code,name', 'template:id,name']);

        return response()->json([
            'message' => 'Template jadwal berhasil ditetapkan ke karyawan dan jadwal sudah dibuat.',
            'assignment' => $assignment,
            'assignment_summary' => [
                'employee' => $assignment->employee ? "{$assignment->employee->employee_code} - {$assignment->employee->name}" : '-',
                'template' => $assignment->template?->name ?? '-',
                'effective_from' => $assignment->effective_from?->toDateString(),
                'effective_until' => $assignment->effective_until?->toDateString(),
            ],
            'generated_count' => $generatedCount,
            'skipped_count' => $skippedCount,
            'conflict_strategy' => $strategy,
            'generated_until' => $until->toDateString(),
        ]);
    }

    public function leavesData(Request $request)
    {
        $query = EmployeeLeave::query()
            ->with(['employee:id,employee_code,name', 'approver:id,name'])
            ->latest('start_date');

        return $this->datatable($query, $request, fn (EmployeeLeave $leave) => [
            'id' => $leave->id,
            'employee_id' => $leave->employee_id,
            'employee' => $leave->employee ? "{$leave->employee->employee_code} - {$leave->employee->name}" : '-',
            'leave_type' => $leave->leave_type,
            'start_date' => $leave->start_date?->format('Y-m-d'),
            'end_date' => $leave->end_date?->format('Y-m-d'),
            'reason' => $leave->reason,
            'proof_image_url' => $this->leaveProofImageUrl($leave),
            'has_proof_image' => !empty($leave->proof_image_path),
            'status' => $leave->status,
            'approved_by' => $leave->approver?->name,
            'approved_at' => $leave->approved_at?->format('Y-m-d H:i:s'),
        ]);
    }

    public function storeLeave(Request $request)
    {
        $validated = $this->validateLeave($request, false);
        $validated['status'] = EmployeeLeave::STATUS_PENDING;
        $storedProofImage = null;

        try {
            if ($request->hasFile('proof_image')) {
                $storedProofImage = $request->file('proof_image')->store('employee-leave-proofs', 'public');
                $validated['proof_image_path'] = 'storage/'.$storedProofImage;
            }

            $leave = EmployeeLeave::create($validated + $this->leaveApprovalAttributes(EmployeeLeave::STATUS_PENDING));
            $this->writeAttendanceAudit($request, 'Menyimpan cuti/izin karyawan', $leave, null, $this->auditSnapshot($leave->fresh()));
        } catch (\Throwable $e) {
            if ($storedProofImage) {
                Storage::disk('public')->delete($storedProofImage);
            }

            throw $e;
        }

        return response()->json(['message' => 'Pengajuan cuti/izin berhasil dibuat dan menunggu approval.', 'leave' => $leave]);
    }

    public function updateLeave(Request $request, EmployeeLeave $leave)
    {
        $before = $this->auditSnapshot($leave);
        if ($leave->status !== EmployeeLeave::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'status' => ['Cuti/izin yang sudah diproses approval tidak bisa diedit. Reject atau hapus lalu buat pengajuan baru jika perlu revisi.'],
            ]);
        }

        $validated = $this->validateLeave($request, false);
        $validated['status'] = EmployeeLeave::STATUS_PENDING;
        $newProofImage = null;
        $oldProofImage = null;

        try {
            if ($request->hasFile('proof_image')) {
                $newProofImage = $request->file('proof_image')->store('employee-leave-proofs', 'public');
                $validated['proof_image_path'] = 'storage/'.$newProofImage;
                $oldProofImage = $this->storageRelativePath($leave->proof_image_path);
            } elseif ($request->boolean('remove_proof_image')) {
                $validated['proof_image_path'] = null;
                $oldProofImage = $this->storageRelativePath($leave->proof_image_path);
            }

            $leave->update($validated + $this->leaveApprovalAttributes(EmployeeLeave::STATUS_PENDING, $leave));
            $leave->refresh();

            if ($oldProofImage) {
                Storage::disk('public')->delete($oldProofImage);
            }
        } catch (\Throwable $e) {
            if ($newProofImage) {
                Storage::disk('public')->delete($newProofImage);
            }

            throw $e;
        }

        $this->writeAttendanceAudit($request, 'Mengubah cuti/izin karyawan', $leave, $before, $this->auditSnapshot($leave));

        return response()->json(['message' => 'Pengajuan cuti/izin berhasil diperbarui.', 'leave' => $leave]);
    }

    public function approveLeave(Request $request, EmployeeLeave $leave)
    {
        return $this->setLeaveApprovalStatus($request, $leave, EmployeeLeave::STATUS_APPROVED);
    }

    public function rejectLeave(Request $request, EmployeeLeave $leave)
    {
        return $this->setLeaveApprovalStatus($request, $leave, EmployeeLeave::STATUS_REJECTED);
    }

    public function destroyLeave(Request $request, EmployeeLeave $leave)
    {
        $before = $this->auditSnapshot($leave);
        $wasApproved = $leave->status === EmployeeLeave::STATUS_APPROVED;
        $employee = $leave->employee;
        $start = $leave->start_date;
        $end = $leave->end_date;
        $proofImage = $this->storageRelativePath($leave->proof_image_path);
        $leave->delete();

        if ($wasApproved && $employee) {
            $this->rebuildAttendanceRange($employee, $start, $end);
        }
        if ($proofImage) {
            Storage::disk('public')->delete($proofImage);
        }
        $this->writeAttendanceAudit($request, 'Menghapus cuti/izin karyawan', $leave, $before, null);

        return response()->json(['message' => 'Cuti/izin berhasil dihapus']);
    }

    public function leaveProofImage(EmployeeLeave $leave)
    {
        $path = $this->storageRelativePath($leave->proof_image_path);

        abort_if(!$path || !Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->response($path, null, [
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    public function rawLogsData(Request $request)
    {
        $query = AttendanceRawLog::query()
            ->with(['employee:id,employee_code,name', 'device:id,name'])
            ->when($request->input('date_from'), fn ($q, $date) => $q->whereDate('scan_at', '>=', $date))
            ->when($request->input('date_to'), fn ($q, $date) => $q->whereDate('scan_at', '<=', $date))
            ->latest('scan_at');

        return $this->datatable($query, $request, fn (AttendanceRawLog $log) => [
            'id' => $log->id,
            'attendance_device_id' => $log->attendance_device_id,
            'employee_id' => $log->employee_id,
            'device' => $log->device?->name ?? '-',
            'employee' => $log->employee ? "{$log->employee->employee_code} - {$log->employee->name}" : 'Belum terhubung',
            'device_user_id' => $log->device_user_id,
            'scan_at' => $log->scan_at?->format('Y-m-d H:i:s'),
            'verify_type' => $log->verify_type,
            'state' => $log->state,
        ]);
    }

    public function storeRawLog(Request $request, AttendanceProcessor $processor, AttendanceLateNotifier $lateNotifier)
    {
        $validated = $request->validate([
            'attendance_device_id' => ['required', 'integer', 'exists:attendance_devices,id'],
            'device_user_id' => ['required', 'string', 'max:100'],
            'scan_at' => ['required', 'date'],
            'verify_type' => ['nullable', 'string', 'max:50'],
            'state' => ['nullable', 'string', 'max:50'],
        ]);

        $device = AttendanceDevice::findOrFail($validated['attendance_device_id']);
        $result = $processor->recordFingerprintScanWithResult(
            $device,
            $validated['device_user_id'],
            Carbon::parse($validated['scan_at']),
            $validated['verify_type'] ?? null,
            $validated['state'] ?? null,
            ['source' => 'manual_input']
        );
        $rawLog = $result['raw_log'];
        $attendance = $result['attendance']?->loadMissing(['employee', 'shift']);
        $isLateCheckIn = $lateNotifier->shouldNotify($attendance, $rawLog);
        if ($isLateCheckIn) {
            $lateNotifier->notifyTelegramIfLate($attendance, $rawLog);
        }
        $this->writeAttendanceAudit($request, 'Menambahkan raw log fingerprint manual', $rawLog, null, [
            'raw_log' => $this->auditSnapshot($rawLog->fresh()),
            'attendance' => $attendance ? $this->auditSnapshot($attendance) : null,
        ]);

        return response()->json([
            'message' => 'Raw log fingerprint berhasil disimpan',
            'raw_log' => $rawLog,
            'attendance' => $attendance ? [
                'id' => $attendance->id,
                'employee' => $attendance->employee ? "{$attendance->employee->employee_code} - {$attendance->employee->name}" : null,
                'attendance_date' => $attendance->attendance_date?->format('Y-m-d'),
                'status' => $attendance->status,
                'late_minutes' => (int) $attendance->late_minutes,
                'check_in_at' => $attendance->check_in_at?->format('Y-m-d H:i:s'),
            ] : null,
            'notification' => [
                'late_check_in' => $isLateCheckIn,
                'message' => $isLateCheckIn ? $lateNotifier->message($attendance) : null,
            ],
        ]);
    }

    public function updateRawLog(Request $request, AttendanceRawLog $rawLog)
    {
        $before = $this->auditSnapshot($rawLog);
        $validated = $request->validate([
            'attendance_device_id' => ['required', 'integer', 'exists:attendance_devices,id'],
            'device_user_id' => ['required', 'string', 'max:100'],
            'scan_at' => ['required', 'date'],
            'verify_type' => ['nullable', 'string', 'max:50'],
            'state' => ['nullable', 'string', 'max:50'],
        ]);

        $scanAt = Carbon::parse($validated['scan_at']);
        $duplicateExists = AttendanceRawLog::query()
            ->where('attendance_device_id', $validated['attendance_device_id'])
            ->where('device_user_id', $validated['device_user_id'])
            ->where('scan_at', $scanAt->toDateTimeString())
            ->whereKeyNot($rawLog->id)
            ->exists();

        if ($duplicateExists) {
            throw ValidationException::withMessages([
                'scan_at' => ['Raw log dengan device, user, dan waktu scan yang sama sudah ada.'],
            ]);
        }

        $oldEmployee = $rawLog->employee;
        $oldScanAt = $rawLog->scan_at?->copy();
        $device = AttendanceDevice::findOrFail($validated['attendance_device_id']);
        $processor = app(AttendanceProcessor::class);
        $employeeId = $processor->employeeIdForDeviceUser($device, $validated['device_user_id']);

        $rawLog->update($validated + [
            'employee_id' => $employeeId,
            'scan_at' => $scanAt,
        ]);
        $rawLog->refresh();

        if ($oldEmployee && $oldScanAt) {
            $processor->rebuildAttendanceForScan($oldEmployee, $oldScanAt);
        }
        if ($rawLog->employee) {
            $processor->rebuildAttendanceForScan($rawLog->employee, $rawLog->scan_at);
        }
        $this->writeAttendanceAudit($request, 'Mengubah raw log fingerprint manual', $rawLog, $before, $this->auditSnapshot($rawLog));

        return response()->json(['message' => 'Raw log fingerprint berhasil diperbarui', 'raw_log' => $rawLog]);
    }

    public function destroyRawLog(Request $request, AttendanceRawLog $rawLog)
    {
        $before = $this->auditSnapshot($rawLog);
        $employee = $rawLog->employee;
        $scanAt = $rawLog->scan_at?->copy();
        $rawLog->delete();

        if ($employee && $scanAt) {
            app(AttendanceProcessor::class)->rebuildAttendanceForScan($employee, $scanAt);
        }
        $this->writeAttendanceAudit($request, 'Menghapus raw log fingerprint manual', $rawLog, $before, null);

        return response()->json(['message' => 'Raw log fingerprint berhasil dihapus']);
    }

    public function attendancesData(Request $request)
    {
        $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'status' => ['nullable', Rule::in($this->attendanceStatusValues())],
            'overtime_status' => ['nullable', Rule::in($this->overtimeStatusValues())],
            'employment_status' => ['nullable', Rule::in([
                Employee::STATUS_ACTIVE,
                Employee::STATUS_INACTIVE,
                'all',
            ])],
            'q' => ['nullable', 'string', 'max:150'],
        ]);

        $dateFrom = $request->input('date_from') ?: today()->toDateString();
        $dateTo = $request->input('date_to') ?: $dateFrom;
        $employmentStatus = $request->input('employment_status', Employee::STATUS_ACTIVE);
        $query = Attendance::query()
            ->with(['employee:id,employee_code,name,employment_status', 'shift:id,name', 'approver:id,name'])
            ->whereDate('attendance_date', '>=', $dateFrom)
            ->whereDate('attendance_date', '<=', $dateTo)
            ->when($employmentStatus !== 'all', fn ($q) => $q->whereHas(
                'employee',
                fn ($employeeQuery) => $employeeQuery->where('employment_status', $employmentStatus)
            ))
            ->when($request->input('employee_id'), fn ($q, $employeeId) => $q->where('employee_id', $employeeId))
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->input('overtime_status'), fn ($q, $status) => $q->where('overtime_status', $status))
            ->when(trim((string) $request->input('q', '')) !== '', function ($q) use ($request) {
                $search = trim((string) $request->input('q'));
                $q->whereHas('employee', fn ($employeeQuery) => $employeeQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('employee_code', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%"));
            })
            ->latest('attendance_date');

        $summaryQuery = clone $query;
        $summary = [
            'total' => (clone $summaryQuery)->count(),
            'present' => (clone $summaryQuery)->whereNotNull('check_in_at')->count(),
            'late' => (clone $summaryQuery)->where('status', Attendance::STATUS_LATE)->count(),
            'incomplete' => (clone $summaryQuery)->where('status', Attendance::STATUS_INCOMPLETE)->count(),
            'absent' => (clone $summaryQuery)->where('status', Attendance::STATUS_ABSENT)->count(),
            'day_off' => (clone $summaryQuery)
                ->whereIn('status', [
                    Attendance::STATUS_DAY_OFF,
                    Attendance::STATUS_HOLIDAY,
                    Attendance::STATUS_LEAVE,
                ])
                ->distinct()
                ->count('employee_id'),
            'overtime_pending' => (clone $summaryQuery)->where('overtime_status', Attendance::OVERTIME_PENDING)->count(),
        ];

        $response = $this->datatable($query, $request, fn (Attendance $attendance) => [
            'id' => $attendance->id,
            'employee_id' => $attendance->employee_id,
            'work_shift_id' => $attendance->work_shift_id,
            'employee' => $attendance->employee ? "{$attendance->employee->employee_code} - {$attendance->employee->name}" : '-',
            'employment_status' => $attendance->employee?->employment_status,
            'attendance_date' => $attendance->attendance_date?->format('Y-m-d'),
            'shift' => $attendance->shift?->name ?? '-',
            'check_in_at' => $attendance->check_in_at?->format('Y-m-d H:i:s'),
            'check_out_at' => $attendance->check_out_at?->format('Y-m-d H:i:s'),
            'late_minutes' => $attendance->late_minutes,
            'early_leave_minutes' => $attendance->early_leave_minutes,
            'work_minutes' => $attendance->work_minutes,
            'overtime_minutes' => $attendance->overtime_minutes,
            'calculated_overtime_minutes' => $attendance->calculated_overtime_minutes,
            'approved_overtime_minutes' => $attendance->approved_overtime_minutes,
            'overtime_status' => $attendance->overtime_status,
            'overtime_note' => $attendance->overtime_note,
            'approved_by' => $attendance->approver?->name,
            'approved_at' => $attendance->approved_at?->format('Y-m-d H:i:s'),
            'status' => $attendance->status,
            'source' => $attendance->source,
            'note' => $attendance->note,
        ]);

        $payload = $response->getData(true);
        $payload['summary'] = $summary;

        return response()->json($payload);
    }

    public function absencesData(Request $request)
    {
        $rows = $this->dailyAttendanceMonitorRows($request);
        $page = max(1, (int) $request->input('page', 1));
        $perPage = min(100, max(1, (int) $request->input('per_page', 30)));
        $total = $rows->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);
        $offset = ($page - 1) * $perPage;

        $data = [
            'data' => $rows->slice($offset, $perPage)->values(),
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => $lastPage,
            'from' => $total ? $offset + 1 : 0,
            'to' => $total ? $offset + min($perPage, $total - $offset) : 0,
        ];
        $data['summary'] = [
            'total' => $total,
            'present_count' => $rows->whereIn('status_key', ['present', 'late'])->count(),
            'not_checked_in_count' => $rows->where('status_key', 'not_checked_in')->count(),
            'absent_count' => $rows->where('status_key', 'absent')->count(),
            'incomplete_count' => $rows->where('status_key', 'incomplete')->count(),
            'off_count' => $rows->whereIn('status_key', ['day_off', 'holiday', 'leave'])->count(),
            'date' => $this->monitorDate($request),
        ];

        return response()->json($data);
    }

    public function exportAbsences(Request $request)
    {
        $date = $this->monitorDate($request);
        $filename = 'monitor_absensi_harian_'.$date.'.xlsx';

        return Excel::download(new AbsentEmployeesExport(
            $this->dailyAttendanceMonitorRows($request)->values()->all()
        ), $filename);
    }

    public function updateAttendance(Request $request, Attendance $attendance)
    {
        $before = $this->auditSnapshot($attendance);
        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'attendance_date' => ['required', 'date'],
            'work_shift_id' => ['nullable', 'integer', 'exists:work_shifts,id'],
            'check_in_at' => ['nullable', 'date'],
            'check_out_at' => ['nullable', 'date'],
            'late_minutes' => ['required', 'integer', 'min:0'],
            'early_leave_minutes' => ['required', 'integer', 'min:0'],
            'work_minutes' => ['required', 'integer', 'min:0'],
            'calculated_overtime_minutes' => ['required', 'integer', 'min:0'],
            'overtime_note' => ['nullable', 'string'],
            'status' => ['required', Rule::in([
                Attendance::STATUS_PRESENT,
                Attendance::STATUS_LATE,
                Attendance::STATUS_ABSENT,
                Attendance::STATUS_LEAVE,
                Attendance::STATUS_HOLIDAY,
                Attendance::STATUS_DAY_OFF,
                Attendance::STATUS_INCOMPLETE,
            ])],
            'source' => ['nullable', 'string', 'max:30'],
            'note' => ['nullable', 'string'],
        ]);

        if (
            !empty($validated['check_in_at'])
            && !empty($validated['check_out_at'])
            && Carbon::parse($validated['check_out_at'])->lessThan(Carbon::parse($validated['check_in_at']))
        ) {
            throw ValidationException::withMessages([
                'check_out_at' => ['Jam pulang tidak boleh lebih awal dari jam masuk.'],
            ]);
        }

        $calculatedChanged = (int) $validated['calculated_overtime_minutes'] !== (int) $attendance->calculated_overtime_minutes;
        $overtimeStatus = $attendance->overtime_status;
        if ((int) $validated['calculated_overtime_minutes'] <= 0) {
            $overtimeStatus = Attendance::OVERTIME_NONE;
        } elseif ($calculatedChanged) {
            $overtimeStatus = Attendance::OVERTIME_PENDING;
        } elseif (!in_array($overtimeStatus, [Attendance::OVERTIME_APPROVED, Attendance::OVERTIME_REJECTED], true)) {
            $overtimeStatus = Attendance::OVERTIME_PENDING;
        }

        $attendance->update(array_merge($validated, [
            'overtime_status' => $overtimeStatus,
            'overtime_minutes' => $overtimeStatus === Attendance::OVERTIME_APPROVED ? (int) $attendance->approved_overtime_minutes : 0,
            'approved_overtime_minutes' => $overtimeStatus === Attendance::OVERTIME_APPROVED ? (int) $attendance->approved_overtime_minutes : null,
            'approved_by' => in_array($overtimeStatus, [Attendance::OVERTIME_APPROVED, Attendance::OVERTIME_REJECTED], true) ? $attendance->approved_by : null,
            'approved_at' => in_array($overtimeStatus, [Attendance::OVERTIME_APPROVED, Attendance::OVERTIME_REJECTED], true) ? $attendance->approved_at : null,
            'source' => $validated['source'] ?? 'manual',
        ]));
        $attendance->refresh();
        $this->writeAttendanceAudit($request, 'Mengubah rekap absensi manual', $attendance, $before, $this->auditSnapshot($attendance));

        return response()->json(['message' => 'Rekap absensi berhasil diperbarui', 'attendance' => $attendance]);
    }

    public function approveOvertime(Request $request, Attendance $attendance)
    {
        $validated = $request->validate([
            'approved_overtime_minutes' => ['required', 'integer', 'min:1'],
            'overtime_note' => ['nullable', 'string'],
        ]);

        if ((int) $attendance->calculated_overtime_minutes <= 0) {
            throw ValidationException::withMessages([
                'approved_overtime_minutes' => ['Absensi ini tidak memiliki lembur terhitung.'],
            ]);
        }

        $before = $this->auditSnapshot($attendance);
        $approvedMinutes = (int) $validated['approved_overtime_minutes'];
        $attendance->update([
            'overtime_minutes' => $approvedMinutes,
            'approved_overtime_minutes' => $approvedMinutes,
            'overtime_status' => Attendance::OVERTIME_APPROVED,
            'overtime_note' => $validated['overtime_note'] ?? $attendance->overtime_note,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);
        $attendance->refresh();
        $this->writeAttendanceAudit($request, 'Approve lembur absensi', $attendance, $before, $this->auditSnapshot($attendance));

        return response()->json(['message' => 'Lembur berhasil di-approve.', 'attendance' => $attendance]);
    }

    public function rejectOvertime(Request $request, Attendance $attendance)
    {
        $validated = $request->validate([
            'overtime_note' => ['nullable', 'string'],
        ]);

        $before = $this->auditSnapshot($attendance);
        $attendance->update([
            'overtime_minutes' => 0,
            'approved_overtime_minutes' => 0,
            'overtime_status' => Attendance::OVERTIME_REJECTED,
            'overtime_note' => $validated['overtime_note'] ?? $attendance->overtime_note,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);
        $attendance->refresh();
        $this->writeAttendanceAudit($request, 'Reject lembur absensi', $attendance, $before, $this->auditSnapshot($attendance));

        return response()->json(['message' => 'Lembur berhasil di-reject.', 'attendance' => $attendance]);
    }

    public function destroyAttendance(Request $request, Attendance $attendance)
    {
        $before = $this->auditSnapshot($attendance);
        $attendance->delete();
        $this->writeAttendanceAudit($request, 'Menghapus rekap absensi manual', $attendance, $before, null);

        return response()->json(['message' => 'Rekap absensi berhasil dihapus']);
    }

    public function machineLogsIndex()
    {
        return view('admin.attendance.machine-logs', [
            'sectionLinks' => $this->sectionLinks(),
            'devices' => AttendanceDevice::query()->orderBy('name')->get(['id', 'name', 'serial_number']),
            'statusOptions' => [
                'success'          => 'Berhasil',
                'heartbeat'        => 'Koneksi Mesin',
                'command_poll'      => 'Mesin Polling',
                'device_command'    => 'Hasil Command Mesin',
                'empty_payload'     => 'Payload Kosong',
                'unsupported_table' => 'Tabel ADMS Lain',
                'unauthorized'     => 'Unauthorized',
                'device_not_found' => 'Device Tidak Ditemukan',
                'validation_error' => 'Validasi Gagal',
                'error'            => 'Error Server',
            ],
        ]);
    }

    public function machineLogsSummary(Request $request)
    {
        $today = now()->toDateString();
        $base  = AttendanceWebhookLog::query()->whereDate('created_at', $today);
        $connectionStatuses = ['heartbeat', 'command_poll', 'device_command'];

        return response()->json([
            'total'     => (clone $base)->whereNotIn('status', $connectionStatuses)->count(),
            'success'   => (clone $base)->where('status', 'success')->count(),
            'failed'    => (clone $base)->whereNotIn('status', array_merge(['success'], $connectionStatuses))->count(),
            'heartbeat' => (clone $base)->whereIn('status', $connectionStatuses)->count(),
        ]);
    }

    public function machineLogsData(Request $request)
    {
        $query = AttendanceWebhookLog::query()
            ->with(['device:id,name,serial_number'])
            ->when($request->input('date_from'), fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($request->input('date_to'), fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->when($request->input('device_id'), fn ($q, $id) => $q->where('attendance_device_id', $id))
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->latest('created_at');

        return $this->simplePaginatedResponse($query, $request, fn (AttendanceWebhookLog $log) => [
            'id' => $log->id,
            'created_at' => $log->created_at?->format('Y-m-d H:i:s'),
            'ip_address' => $log->ip_address,
            'device' => $log->device ? "{$log->device->name}" : ($log->serial_number ? "SN: {$log->serial_number}" : '-'),
            'device_user_id' => $log->device_user_id ?? '-',
            'http_status' => $log->http_status,
            'status' => $log->status,
            'raw_log_id' => $log->raw_log_id,
            'request_payload' => $log->request_payload,
            'response_payload' => $log->response_payload,
        ]);
    }

    private function simplePaginatedResponse($query, Request $request, callable $mapper)
    {
        $page = max(1, (int) $request->input('page', 1));
        $perPage = min(100, max(1, (int) $request->input('per_page', 30)));
        $total = (clone $query)->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);
        $offset = ($page - 1) * $perPage;

        $rows = $query->skip($offset)->take($perPage)->get()->map($mapper)->values();

        return response()->json([
            'data' => $rows,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => $lastPage,
            'from' => $total ? $offset + 1 : 0,
            'to' => $total ? $offset + $rows->count() : 0,
        ]);
    }

    private function dailyAttendanceMonitorRows(Request $request)
    {
        $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'status' => ['nullable', Rule::in($this->dailyAttendanceStatusValues())],
            'q' => ['nullable', 'string', 'max:150'],
        ]);

        $date = $this->monitorDate($request);
        $search = trim((string) $request->input('q', ''));
        $status = trim((string) $request->input('status', ''));

        $schedules = EmployeeSchedule::query()
            ->with('shift:id,name,start_time,end_time,late_tolerance_minutes')
            ->whereDate('schedule_date', $date)
            ->when($request->input('employee_id'), fn ($query, $employeeId) => $query->where('employee_id', $employeeId))
            ->orderBy('employee_id')
            ->get()
            ->keyBy('employee_id');

        $attendances = Attendance::query()
            ->with('shift:id,name,start_time,end_time,late_tolerance_minutes')
            ->whereDate('attendance_date', $date)
            ->when($request->input('employee_id'), fn ($query, $employeeId) => $query->where('employee_id', $employeeId))
            ->get()
            ->keyBy('employee_id');

        $holiday = Holiday::query()
            ->whereDate('holiday_date', $date)
            ->first();

        $leaves = EmployeeLeave::query()
            ->where('status', EmployeeLeave::STATUS_APPROVED)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->when($request->input('employee_id'), fn ($query, $employeeId) => $query->where('employee_id', $employeeId))
            ->get()
            ->keyBy('employee_id');

        $employees = Employee::query()
            ->with(['area:id,code,name', 'positionRelation:id,name'])
            ->active()
            ->where(function ($joinDateQuery) use ($date) {
                $joinDateQuery
                    ->whereNull('join_date')
                    ->orWhereDate('join_date', '<=', $date);
            })
            ->when($request->input('employee_id'), fn ($query, $employeeId) => $query->whereKey($employeeId))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($employeeQuery) use ($search) {
                    $employeeQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('employee_code', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->get();

        return $employees
            ->map(fn (Employee $employee) => $this->dailyAttendanceMonitorRow(
                $date,
                $employee,
                $schedules->get($employee->id),
                $attendances->get($employee->id),
                $leaves->get($employee->id),
                $holiday
            ))
            ->when($status !== '', fn ($collection) => $collection->where('status_key', $status))
            ->values();
    }

    private function dailyAttendanceMonitorRow(
        string $date,
        ?Employee $employee,
        ?EmployeeSchedule $schedule,
        ?Attendance $attendance,
        ?EmployeeLeave $leave,
        ?Holiday $holiday
    ): array
    {
        $scheduleType = $this->dailyMonitorScheduleType($schedule, $attendance, $leave, $holiday);
        $shift = $scheduleType === EmployeeSchedule::TYPE_WORK
            ? ($schedule?->shift ?? $attendance?->shift)
            : null;
        $statusKey = $this->dailyAttendanceStatusKey($date, $schedule, $attendance, $leave, $holiday);
        $statusLabels = $this->dailyAttendanceStatusLabels();
        $scheduleLabels = [
            EmployeeSchedule::TYPE_WORK => 'Masuk',
            EmployeeSchedule::TYPE_DAY_OFF => 'Libur',
            EmployeeSchedule::TYPE_HOLIDAY => 'Libur Perusahaan',
            EmployeeSchedule::TYPE_LEAVE => 'Cuti/Izin',
            'unscheduled' => 'Tidak Ada Jadwal',
        ];

        return [
            'employee_id' => $employee?->id,
            'employee_code' => $employee?->employee_code ?? '-',
            'employee_name' => $employee?->name ?? '-',
            'employee' => $employee ? "{$employee->employee_code} - {$employee->name}" : '-',
            'position' => $employee?->positionRelation?->name ?? $employee?->position ?? '-',
            'area' => $employee?->area ? "{$employee->area->code} - {$employee->area->name}" : '-',
            'attendance_date' => $date,
            'schedule_type' => $scheduleType,
            'schedule_type_label' => $this->dailyMonitorScheduleLabel($scheduleType, $holiday, $scheduleLabels),
            'shift' => $shift?->name ?? '-',
            'shift_time' => $shift ? substr((string) $shift->start_time, 0, 5).' - '.substr((string) $shift->end_time, 0, 5) : '-',
            'check_in_at' => $attendance?->check_in_at?->format('H:i') ?? '-',
            'check_out_at' => $attendance?->check_out_at?->format('H:i') ?? '-',
            'status_key' => $statusKey,
            'status_label' => $statusLabels[$statusKey] ?? $statusKey,
            'late_minutes' => (int) ($attendance?->late_minutes ?? 0),
            'early_leave_minutes' => (int) ($attendance?->early_leave_minutes ?? 0),
            'note' => $attendance?->note
                ?: ($leave ? 'Cuti/Izin: '.$leave->leave_type : null)
                ?: $holiday?->name
                ?: $schedule?->note
                ?: '-',
        ];
    }

    private function dailyMonitorScheduleType(
        ?EmployeeSchedule $schedule,
        ?Attendance $attendance,
        ?EmployeeLeave $leave,
        ?Holiday $holiday
    ): string
    {
        return match ($attendance?->status) {
            Attendance::STATUS_LEAVE => EmployeeSchedule::TYPE_LEAVE,
            Attendance::STATUS_HOLIDAY => EmployeeSchedule::TYPE_HOLIDAY,
            Attendance::STATUS_DAY_OFF => EmployeeSchedule::TYPE_DAY_OFF,
            default => match (true) {
                (bool) $leave => EmployeeSchedule::TYPE_LEAVE,
                (bool) $holiday => EmployeeSchedule::TYPE_HOLIDAY,
                default => $schedule?->schedule_type ?? 'unscheduled',
            },
        };
    }

    private function dailyMonitorScheduleLabel(string $scheduleType, ?Holiday $holiday, array $scheduleLabels): string
    {
        if ($scheduleType === EmployeeSchedule::TYPE_HOLIDAY && $holiday) {
            return $holiday->type === 'national' ? 'Libur Nasional' : 'Libur Perusahaan';
        }

        return $scheduleLabels[$scheduleType] ?? $scheduleType;
    }

    private function dailyAttendanceStatusKey(
        string $date,
        ?EmployeeSchedule $schedule,
        ?Attendance $attendance,
        ?EmployeeLeave $leave,
        ?Holiday $holiday
    ): string
    {
        if ($attendance?->status) {
            return $attendance->status;
        }

        if ($leave) {
            return Attendance::STATUS_LEAVE;
        }

        if ($holiday) {
            return Attendance::STATUS_HOLIDAY;
        }

        if (!$schedule) {
            return 'unscheduled';
        }

        if ($schedule->schedule_type !== EmployeeSchedule::TYPE_WORK) {
            return $schedule->schedule_type;
        }

        $shift = $schedule->shift;
        if (!$shift) {
            return 'not_checked_in';
        }

        $lateCutoff = Carbon::parse($date.' '.$shift->start_time)->addMinutes((int) $shift->late_tolerance_minutes);
        if (Carbon::parse($date)->isFuture() || (Carbon::parse($date)->isToday() && now()->lessThanOrEqualTo($lateCutoff))) {
            return 'not_checked_in';
        }

        return Attendance::STATUS_ABSENT;
    }

    private function dailyAttendanceStatusLabels(): array
    {
        return [
            Attendance::STATUS_PRESENT => 'Hadir',
            Attendance::STATUS_LATE => 'Terlambat',
            Attendance::STATUS_ABSENT => 'Alfa',
            Attendance::STATUS_LEAVE => 'Cuti/Izin',
            Attendance::STATUS_HOLIDAY => 'Libur',
            Attendance::STATUS_DAY_OFF => 'Libur',
            Attendance::STATUS_INCOMPLETE => 'Belum Check-out',
            EmployeeSchedule::TYPE_LEAVE => 'Cuti/Izin',
            EmployeeSchedule::TYPE_HOLIDAY => 'Libur Perusahaan',
            EmployeeSchedule::TYPE_DAY_OFF => 'Libur',
            'not_checked_in' => 'Belum Check-in',
            'unscheduled' => 'Tidak Ada Jadwal',
        ];
    }

    private function attendanceStatusValues(): array
    {
        return [
            Attendance::STATUS_PRESENT,
            Attendance::STATUS_LATE,
            Attendance::STATUS_ABSENT,
            Attendance::STATUS_LEAVE,
            Attendance::STATUS_HOLIDAY,
            Attendance::STATUS_DAY_OFF,
            Attendance::STATUS_INCOMPLETE,
        ];
    }

    private function overtimeStatusValues(): array
    {
        return [
            Attendance::OVERTIME_NONE,
            Attendance::OVERTIME_PENDING,
            Attendance::OVERTIME_APPROVED,
            Attendance::OVERTIME_REJECTED,
        ];
    }

    private function dailyAttendanceStatusValues(): array
    {
        return [
            ...$this->attendanceStatusValues(),
            'not_checked_in',
            'unscheduled',
        ];
    }

    private function monitorDate(Request $request): string
    {
        return Carbon::parse($request->input('date') ?: now()->toDateString())->toDateString();
    }

    private function parseOptionalDate(mixed $value): ?string
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function validateEmployee(Request $request, ?Employee $employee = null): array
    {
        $validated = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id', Rule::unique('employees', 'user_id')->ignore($employee?->id)],
            'area_id' => ['nullable', 'integer', 'exists:areas,id'],
            'position_id' => ['nullable', 'integer', 'exists:employee_positions,id'],
            'employee_code' => ['nullable', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
            'position' => ['nullable', 'string', 'max:100'],
            'join_date' => ['nullable', 'date'],
            'employment_status' => ['required', Rule::in([Employee::STATUS_ACTIVE, Employee::STATUS_INACTIVE])],
        ]);

        $validated['employee_code'] = trim((string) ($validated['employee_code'] ?? ''));

        return $validated;
    }

    private function generateEmployeeCode(array &$reserved = []): string
    {
        $prefix = 'K';
        $width = 4;
        $maxNumber = Employee::query()
            ->where('employee_code', 'like', $prefix.'%')
            ->pluck('employee_code')
            ->map(function ($code) use ($prefix) {
                $suffix = substr((string) $code, strlen($prefix));
                return ctype_digit($suffix) ? (int) $suffix : 0;
            })
            ->max() ?? 0;

        $number = max(0, (int) $maxNumber) + 1;
        do {
            $code = $prefix.str_pad((string) $number, $width, '0', STR_PAD_LEFT);
            $key = strtolower($code);
            $exists = isset($reserved[$key]) || Employee::query()->where('employee_code', $code)->exists();
            $number++;
        } while ($exists);

        $reserved[$key] = true;

        return $code;
    }

    private function parseEmployeeImportDate(mixed $value, int|string $rowNo): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if (is_numeric($value)) {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value)
                    ->format('Y-m-d');
            }

            return Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'file' => "Baris {$rowNo}: Format tanggal masuk tidak valid ({$value})",
            ]);
        }
    }

    private function validateDevice(Request $request, ?AttendanceDevice $device = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'serial_number' => ['nullable', 'string', 'max:100', Rule::unique('attendance_devices', 'serial_number')->ignore($device?->id)],
            'ip_address' => ['nullable', 'string', 'max:45'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'location' => ['nullable', 'string', 'max:150'],
            'device_type' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $validated['is_active'] = $request->boolean('is_active');

        return $validated;
    }

    private function validateFingerprint(Request $request, ?EmployeeFingerprint $fingerprint = null): array
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'integer', $this->activeEmployeeExistsRule()],
            'attendance_device_id' => ['nullable', 'integer', 'exists:attendance_devices,id'],
            'device_user_id' => [
                'required',
                'string',
                'max:100',
                Rule::unique('employee_fingerprints', 'device_user_id')
                    ->where(fn ($q) => $q->where('attendance_device_id', $request->input('attendance_device_id')))
                    ->ignore($fingerprint?->id),
            ],
            'fingerprint_uid' => ['nullable', 'string', 'max:150'],
            'is_active' => ['nullable', 'boolean'],
            'enrolled_at' => ['nullable', 'date'],
        ]);
        $validated['is_active'] = $request->boolean('is_active', true);

        return $validated;
    }

    private function validateShift(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'break_start_time' => ['nullable', 'date_format:H:i'],
            'break_end_time' => ['nullable', 'date_format:H:i'],
            'late_tolerance_minutes' => ['required', 'integer', 'min:0'],
            'checkout_tolerance_minutes' => ['required', 'integer', 'min:0'],
            'overtime_start_after_minutes' => ['required', 'integer', 'min:0'],
            'minimum_overtime_minutes' => ['required', 'integer', 'min:0'],
            'crosses_midnight' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $validated['crosses_midnight'] = $request->boolean('crosses_midnight');
        $validated['is_active'] = $request->boolean('is_active', true);

        return $validated;
    }

    private function validateSchedule(Request $request): array
    {
        return $request->validate([
            'employee_id' => ['required', 'integer', $this->activeEmployeeExistsRule()],
            'work_shift_id' => [Rule::requiredIf($request->input('schedule_type') === EmployeeSchedule::TYPE_WORK), 'nullable', 'integer', 'exists:work_shifts,id'],
            'schedule_date' => ['required', 'date', 'after_or_equal:today'],
            'schedule_type' => ['required', Rule::in([
                EmployeeSchedule::TYPE_WORK,
                EmployeeSchedule::TYPE_DAY_OFF,
                EmployeeSchedule::TYPE_HOLIDAY,
                EmployeeSchedule::TYPE_LEAVE,
            ])],
            'note' => ['nullable', 'string'],
        ]);
    }

    private function ensureScheduleIsEditable(EmployeeSchedule $schedule): void
    {
        if ($schedule->schedule_date?->lt(today())) {
            throw ValidationException::withMessages([
                'schedule_date' => ['Jadwal yang tanggalnya sudah terlewat tidak dapat diubah atau dihapus.'],
            ]);
        }
    }

    private function validateLeave(Request $request, bool $allowStatus = true): array
    {
        $rules = [
            'employee_id' => ['required', 'integer', $this->activeEmployeeExistsRule()],
            'leave_type' => ['required', 'string', 'max:30'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string'],
            'proof_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_proof_image' => ['nullable', 'boolean'],
        ];

        if ($allowStatus) {
            $rules['status'] = ['required', Rule::in([
                EmployeeLeave::STATUS_PENDING,
                EmployeeLeave::STATUS_APPROVED,
                EmployeeLeave::STATUS_REJECTED,
            ])];
        }

        $validated = $request->validate($rules);
        $status = $validated['status'] ?? EmployeeLeave::STATUS_PENDING;

        $leaveId = $request->route('leave') instanceof EmployeeLeave
            ? $request->route('leave')->id
            : null;

        if ($status !== EmployeeLeave::STATUS_REJECTED) {
            $overlapExists = EmployeeLeave::query()
                ->where('employee_id', $validated['employee_id'])
                ->whereIn('status', [EmployeeLeave::STATUS_PENDING, EmployeeLeave::STATUS_APPROVED])
                ->when($leaveId, fn ($query) => $query->whereKeyNot($leaveId))
                ->whereDate('start_date', '<=', $validated['end_date'])
                ->whereDate('end_date', '>=', $validated['start_date'])
                ->exists();

            if ($overlapExists) {
                throw ValidationException::withMessages([
                    'start_date' => ['Karyawan sudah memiliki cuti/izin pending atau approved pada rentang tanggal tersebut.'],
                ]);
            }
        }

        unset($validated['proof_image'], $validated['remove_proof_image']);

        return $validated;
    }

    private function activeEmployeeExistsRule()
    {
        return Rule::exists('employees', 'id')
            ->where('employment_status', Employee::STATUS_ACTIVE);
    }

    private function leaveProofImageUrl(EmployeeLeave $leave): ?string
    {
        if (!$leave->proof_image_path) {
            return null;
        }

        return route('admin.attendance.leaves.proof-image', $leave);
    }

    private function storageRelativePath(?string $path): ?string
    {
        if (!$path || !str_starts_with($path, 'storage/')) {
            return null;
        }

        return Str::after($path, 'storage/');
    }

    private function setLeaveApprovalStatus(Request $request, EmployeeLeave $leave, string $status)
    {
        if (!in_array($status, [EmployeeLeave::STATUS_APPROVED, EmployeeLeave::STATUS_REJECTED], true)) {
            throw ValidationException::withMessages([
                'status' => ['Status approval cuti/izin tidak valid.'],
            ]);
        }

        if ($leave->status === $status) {
            return response()->json([
                'message' => $status === EmployeeLeave::STATUS_APPROVED
                    ? 'Cuti/izin sudah approved.'
                    : 'Cuti/izin sudah rejected.',
            ]);
        }

        if ($status === EmployeeLeave::STATUS_APPROVED) {
            $overlapExists = EmployeeLeave::query()
                ->where('employee_id', $leave->employee_id)
                ->where('status', EmployeeLeave::STATUS_APPROVED)
                ->whereKeyNot($leave->id)
                ->whereDate('start_date', '<=', $leave->end_date)
                ->whereDate('end_date', '>=', $leave->start_date)
                ->exists();

            if ($overlapExists) {
                throw ValidationException::withMessages([
                    'status' => ['Karyawan sudah memiliki cuti/izin approved pada rentang tanggal tersebut.'],
                ]);
            }
        }

        $before = $this->auditSnapshot($leave);
        $wasApproved = $leave->status === EmployeeLeave::STATUS_APPROVED;
        $employee = $leave->employee;
        $start = $leave->start_date;
        $end = $leave->end_date;

        $leave->update([
            'status' => $status,
            ...$this->leaveApprovalAttributes($status, $leave),
        ]);
        $leave->refresh();

        if ($wasApproved || $status === EmployeeLeave::STATUS_APPROVED) {
            $this->rebuildAttendanceRange($employee, $start, $end);
        }
        $this->writeAttendanceAudit(
            $request,
            $status === EmployeeLeave::STATUS_APPROVED ? 'Approve cuti/izin karyawan' : 'Reject cuti/izin karyawan',
            $leave,
            $before,
            $this->auditSnapshot($leave)
        );

        return response()->json([
            'message' => $status === EmployeeLeave::STATUS_APPROVED
                ? 'Cuti/izin berhasil di-approve dan rekap absensi diperbarui.'
                : 'Cuti/izin berhasil di-reject dan rekap absensi diperbarui.',
            'leave' => $leave,
        ]);
    }

    private function leaveApprovalAttributes(string $status, ?EmployeeLeave $leave = null): array
    {
        if ($status !== EmployeeLeave::STATUS_APPROVED) {
            return [
                'approved_by' => null,
                'approved_at' => null,
            ];
        }

        if ($leave?->status === EmployeeLeave::STATUS_APPROVED && $leave->approved_by && $leave->approved_at) {
            return [
                'approved_by' => $leave->approved_by,
                'approved_at' => $leave->approved_at,
            ];
        }

        return [
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ];
    }

    private function rebuildAttendanceRange(?Employee $employee, Carbon|string|null $start, Carbon|string|null $end): void
    {
        if (!$employee || !$start || !$end) {
            return;
        }

        $current = $start instanceof Carbon ? $start->copy()->startOfDay() : Carbon::parse($start)->startOfDay();
        $until = $end instanceof Carbon ? $end->copy()->startOfDay() : Carbon::parse($end)->startOfDay();

        while ($current->lte($until)) {
            app(AttendanceProcessor::class)->rebuildDailyAttendance($employee, $current);
            $current->addDay();
        }
    }

    private function rebuildAttendanceForHolidayDates(array $dates): int
    {
        $dateKeys = collect($dates)
            ->filter()
            ->map(fn ($date) => $date instanceof Carbon ? $date->toDateString() : Carbon::parse($date)->toDateString())
            ->unique()
            ->values();

        if ($dateKeys->isEmpty()) {
            return 0;
        }

        $rebuiltCount = 0;
        $processor = app(AttendanceProcessor::class);

        foreach ($dateKeys as $dateKey) {
            Employee::query()
                ->active()
                ->where(function ($query) use ($dateKey) {
                    $query->whereNull('join_date')
                        ->orWhereDate('join_date', '<=', $dateKey);
                })
                ->orderBy('id')
                ->chunkById(100, function ($employees) use ($processor, $dateKey, &$rebuiltCount) {
                    foreach ($employees as $employee) {
                        $processor->rebuildDailyAttendance($employee, $dateKey);
                        $rebuiltCount++;
                    }
                });
        }

        return $rebuiltCount;
    }

    private function syncTemplateDays(WeeklyScheduleTemplate $template, array $days): void
    {
        $template->days()->delete();
        foreach ($days as $day) {
            if (($day['schedule_type'] ?? '') === EmployeeSchedule::TYPE_WORK && empty($day['work_shift_id'])) {
                throw ValidationException::withMessages([
                    'days' => ['Hari kerja pada template wajib memiliki shift.'],
                ]);
            }

            WeeklyScheduleTemplateDay::create([
                'weekly_schedule_template_id' => $template->id,
                'day_of_week' => $day['day_of_week'],
                'schedule_type' => $day['schedule_type'],
                'work_shift_id' => ($day['schedule_type'] ?? '') === EmployeeSchedule::TYPE_WORK ? ($day['work_shift_id'] ?? null) : null,
            ]);
        }
    }

    private function detectTemplateAssignmentConflicts(int $employeeId, Carbon $from, Carbon $until): array
    {
        $existingScheduleDates = EmployeeSchedule::query()
            ->where('employee_id', $employeeId)
            ->whereDate('schedule_date', '>=', $from->toDateString())
            ->whereDate('schedule_date', '<=', $until->toDateString())
            ->orderBy('schedule_date')
            ->pluck('schedule_date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->values();

        $assignmentCount = EmployeeScheduleAssignment::query()
            ->where('employee_id', $employeeId)
            ->whereDate('effective_from', '<=', $until->toDateString())
            ->where(function ($query) use ($from) {
                $query->whereNull('effective_until')
                    ->orWhereDate('effective_until', '>=', $from->toDateString());
            })
            ->count();

        return [
            'schedule_count' => $existingScheduleDates->count(),
            'assignment_count' => $assignmentCount,
            'date_samples' => $existingScheduleDates->take(8)->all(),
            'period' => [
                'from' => $from->toDateString(),
                'until' => $until->toDateString(),
            ],
        ];
    }

    private function materializeTemplateSchedules(EmployeeScheduleAssignment $assignment, Carbon $from, Carbon $until, string $strategy = 'overwrite'): array
    {
        $templateDays = WeeklyScheduleTemplateDay::query()
            ->where('weekly_schedule_template_id', $assignment->weekly_schedule_template_id)
            ->get()
            ->keyBy('day_of_week');

        $employee = Employee::findOrFail($assignment->employee_id);
        $current = $from->copy();
        $generatedCount = 0;
        $skippedCount = 0;

        while ($current->lte($until)) {
            $templateDay = $templateDays->get((int) $current->dayOfWeekIso);

            if ($templateDay) {
                $scheduleDate = $current->toDateString();
                $attributes = [
                    'employee_id' => $assignment->employee_id,
                    'schedule_date' => $scheduleDate,
                ];
                $values = [
                    'employee_schedule_assignment_id' => $assignment->id,
                    'work_shift_id' => $templateDay->schedule_type === EmployeeSchedule::TYPE_WORK ? $templateDay->work_shift_id : null,
                    'schedule_type' => $templateDay->schedule_type,
                    'note' => 'Dibuat dari template jadwal',
                    'created_by' => auth()->id(),
                ];

                if ($strategy === 'skip_existing' && EmployeeSchedule::query()->where($attributes)->exists()) {
                    $skippedCount++;
                    $current->addDay();
                    continue;
                }

                $schedule = EmployeeSchedule::query()->updateOrCreate($attributes, $values);

                app(AttendanceProcessor::class)->rebuildDailyAttendance($employee, $schedule->schedule_date);
                $generatedCount++;
            }

            $current->addDay();
        }

        return [
            'generated' => $generatedCount,
            'skipped' => $skippedCount,
        ];
    }

    private function syncGeneratedSchedulesForTemplate(WeeklyScheduleTemplate $template): void
    {
        $templateDays = $template->days()
            ->get()
            ->keyBy('day_of_week');

        $template->assignments()
            ->whereDate('effective_until', '>=', today())
            ->with('employee')
            ->get()
            ->each(function (EmployeeScheduleAssignment $assignment) use ($templateDays) {
                $from = $assignment->effective_from->greaterThan(today())
                    ? $assignment->effective_from->copy()
                    : today();
                $until = $assignment->effective_until?->copy();

                if (!$until || $from->gt($until) || !$assignment->employee) {
                    return;
                }

                $current = $from;
                while ($current->lte($until)) {
                    $activeAssignmentId = EmployeeScheduleAssignment::query()
                        ->where('employee_id', $assignment->employee_id)
                        ->whereDate('effective_from', '<=', $current)
                        ->whereDate('effective_until', '>=', $current)
                        ->latest('effective_from')
                        ->value('id');

                    if ((int) $activeAssignmentId !== $assignment->id) {
                        $current->addDay();
                        continue;
                    }

                    $templateDay = $templateDays->get((int) $current->dayOfWeekIso);
                    $schedule = EmployeeSchedule::query()
                        ->where('employee_id', $assignment->employee_id)
                        ->whereDate('schedule_date', $current)
                        ->where(function ($query) use ($assignment) {
                            $query->where('employee_schedule_assignment_id', $assignment->id)
                                ->orWhere(function ($legacyQuery) {
                                    $legacyQuery->whereNull('employee_schedule_assignment_id')
                                        ->where('note', 'Dibuat dari template jadwal');
                                });
                        })
                        ->first();

                    if (!$templateDay) {
                        if ($schedule) {
                            $schedule->delete();
                            app(AttendanceProcessor::class)->rebuildDailyAttendance($assignment->employee, $current);
                        }
                        $current->addDay();
                        continue;
                    }

                    if ($schedule) {
                        $schedule->update([
                            'employee_schedule_assignment_id' => $assignment->id,
                            'work_shift_id' => $templateDay->schedule_type === EmployeeSchedule::TYPE_WORK
                                ? $templateDay->work_shift_id
                                : null,
                            'schedule_type' => $templateDay->schedule_type,
                            'note' => 'Dibuat dari template jadwal',
                        ]);
                        app(AttendanceProcessor::class)->rebuildDailyAttendance($assignment->employee, $current);
                    } elseif ($templateDay && !EmployeeSchedule::query()
                        ->where('employee_id', $assignment->employee_id)
                        ->whereDate('schedule_date', $current)
                        ->exists()) {
                        EmployeeSchedule::create([
                            'employee_id' => $assignment->employee_id,
                            'employee_schedule_assignment_id' => $assignment->id,
                            'work_shift_id' => $templateDay->schedule_type === EmployeeSchedule::TYPE_WORK
                                ? $templateDay->work_shift_id
                                : null,
                            'schedule_date' => $current->toDateString(),
                            'schedule_type' => $templateDay->schedule_type,
                            'note' => 'Dibuat dari template jadwal',
                            'created_by' => auth()->id(),
                        ]);
                        app(AttendanceProcessor::class)->rebuildDailyAttendance($assignment->employee, $current);
                    }

                    $current->addDay();
                }
            });
    }

    private function applySearch($query, Request $request, array $columns): void
    {
        $search = trim((string) $request->input('q', ''));
        if ($search === '') {
            return;
        }

        $query->where(function ($q) use ($columns, $search) {
            foreach ($columns as $index => $column) {
                $method = $index === 0 ? 'where' : 'orWhere';
                $q->{$method}($column, 'like', "%{$search}%");
            }
        });
    }

    private function datatable($query, Request $request, callable $mapper)
    {
        $recordsTotal = (clone $query)->count();
        $recordsFiltered = (clone $query)->count();
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $query->get()->map($mapper)->values(),
        ]);
    }

    private function timeValue(?string $value): ?string
    {
        return $value ? substr($value, 0, 5) : null;
    }

    private function writeAttendanceAudit(Request $request, string $action, ?Model $subject, ?array $before, ?array $after): void
    {
        try {
            ActivityLog::create([
                'user_id' => auth()->id(),
                'action' => $action,
                'route_name' => $request->route()?->getName(),
                'method' => strtoupper($request->method()),
                'url' => $request->fullUrl(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'payload' => [
                    'ringkasan' => [
                        'hasil' => 'Berhasil',
                        'aktivitas' => $action,
                        'modul' => 'Attendance',
                        'aksi' => $action,
                        'target' => $subject ? class_basename($subject).' #'.$subject->getKey() : '-',
                    ],
                    'audit' => [
                        'model' => $subject ? get_class($subject) : null,
                        'model_id' => $subject?->getKey(),
                        'before' => $before,
                        'after' => $after,
                    ],
                ],
            ]);
        } catch (\Throwable) {
            // Audit tambahan tidak boleh menggagalkan proses utama.
        }
    }

    private function auditSnapshot(?Model $model): ?array
    {
        if (!$model) {
            return null;
        }

        return collect($model->getAttributes())
            ->map(fn ($value) => $value instanceof \DateTimeInterface ? $value->format('Y-m-d H:i:s') : $value)
            ->all();
    }
}
