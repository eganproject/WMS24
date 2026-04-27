<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Attendance;
use App\Models\AttendanceDevice;
use App\Models\AttendanceRawLog;
use App\Models\Employee;
use App\Models\EmployeeFingerprint;
use App\Models\EmployeeLeave;
use App\Models\EmployeePosition;
use App\Models\EmployeeSchedule;
use App\Models\EmployeeScheduleAssignment;
use App\Models\Holiday;
use App\Models\User;
use App\Models\WeeklyScheduleTemplate;
use App\Models\WeeklyScheduleTemplateDay;
use App\Models\WorkShift;
use App\Support\AttendanceLateNotifier;
use App\Support\AttendanceProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AttendanceController extends Controller
{
    public function employeeSchedule()
    {
        return view('admin.attendance.employee-schedule', [
            'employees' => Employee::query()->orderBy('name')->get(['id', 'employee_code', 'name']),
        ]);
    }

    public function index()
    {
        return view('admin.attendance.index', [
            'areas' => Area::query()->orderBy('code')->get(['id', 'code', 'name']),
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'email']),
            'positions' => EmployeePosition::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'employees' => Employee::query()->orderBy('name')->get(['id', 'employee_code', 'name']),
            'devices' => AttendanceDevice::query()->orderBy('name')->get(['id', 'name']),
            'shifts' => WorkShift::query()->orderBy('name')->get(['id', 'name', 'start_time', 'end_time']),
            'templates' => WeeklyScheduleTemplate::query()->orderBy('name')->get(['id', 'name']),
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
        $employee = Employee::create($validated);

        return response()->json(['message' => 'Karyawan berhasil dibuat', 'employee' => $employee]);
    }

    public function updateEmployee(Request $request, Employee $employee)
    {
        $validated = $this->validateEmployee($request, $employee);
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
            'crosses_midnight' => $shift->crosses_midnight,
            'is_active' => $shift->is_active,
        ]);
    }

    public function storeShift(Request $request)
    {
        $shift = WorkShift::create($this->validateShift($request));

        return response()->json(['message' => 'Shift berhasil dibuat', 'shift' => $shift]);
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
        $query = EmployeeSchedule::query()
            ->with(['employee:id,employee_code,name', 'shift:id,name'])
            ->when($request->input('employee_id'), fn ($q, $id) => $q->where('employee_id', $id))
            ->when($request->input('date_from'), fn ($q, $date) => $q->whereDate('schedule_date', '>=', $date))
            ->when($request->input('date_to'), fn ($q, $date) => $q->whereDate('schedule_date', '<=', $date))
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

        return $this->datatable($query, $request, fn (EmployeeSchedule $schedule) => [
            'id' => $schedule->id,
            'employee_id' => $schedule->employee_id,
            'work_shift_id' => $schedule->work_shift_id,
            'employee' => $schedule->employee ? "{$schedule->employee->employee_code} - {$schedule->employee->name}" : '-',
            'schedule_date' => $schedule->schedule_date?->format('Y-m-d'),
            'schedule_type' => $schedule->schedule_type,
            'shift' => $schedule->shift?->name ?? '-',
            'note' => $schedule->note,
        ]);
    }

    public function calendarEvents(Request $request)
    {
        $start = Carbon::parse($request->input('start', now()->startOfMonth()->toDateString()))->toDateString();
        $end = Carbon::parse($request->input('end', now()->endOfMonth()->toDateString()))->toDateString();
        $employeeId = $request->integer('employee_id') ?: null;
        $eventsByDate = [];

        EmployeeSchedule::query()
            ->with(['employee:id,employee_code,name', 'shift:id,name'])
            ->when($employeeId, fn ($query) => $query->where('employee_id', $employeeId))
            ->whereDate('schedule_date', '>=', $start)
            ->whereDate('schedule_date', '<=', $end)
            ->get()
            ->each(function (EmployeeSchedule $schedule) use (&$eventsByDate) {
                $label = match ($schedule->schedule_type) {
                    EmployeeSchedule::TYPE_WORK => 'Masuk',
                    EmployeeSchedule::TYPE_DAY_OFF => 'Libur',
                    EmployeeSchedule::TYPE_HOLIDAY => 'Libur Perusahaan',
                    EmployeeSchedule::TYPE_LEAVE => 'Cuti/Izin',
                    default => $schedule->schedule_type,
                };
                $date = $schedule->schedule_date?->toDateString();

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

        Holiday::query()
            ->whereDate('holiday_date', '>=', $start)
            ->whereDate('holiday_date', '<=', $end)
            ->get()
            ->each(function (Holiday $holiday) use (&$eventsByDate) {
                $this->addCalendarSummary($eventsByDate, $holiday->holiday_date?->toDateString(), 'company_holiday', [
                    'label' => 'Libur Perusahaan',
                    'color' => '#f1416c',
                    'textColor' => '#ffffff',
                    'detail' => $holiday->name,
                ]);
            });

        EmployeeLeave::query()
            ->with('employee:id,employee_code,name')
            ->when($employeeId, fn ($query) => $query->where('employee_id', $employeeId))
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $end)
            ->whereDate('end_date', '>=', $start)
            ->get()
            ->each(function (EmployeeLeave $leave) use (&$eventsByDate, $start, $end) {
                $current = $leave->start_date->copy()->max(Carbon::parse($start));
                $until = $leave->end_date->copy()->min(Carbon::parse($end));

                while ($current->lte($until)) {
                    $this->addCalendarSummary($eventsByDate, $current->toDateString(), 'approved_leave', [
                        'label' => 'Cuti/Izin Disetujui',
                        'color' => '#ffc700',
                        'textColor' => '#181c32',
                        'detail' => trim(implode(' ', array_filter([
                            $leave->employee ? "{$leave->employee->employee_code} - {$leave->employee->name}" : null,
                            $leave->reason ? "- {$leave->reason}" : null,
                        ]))),
                    ]);
                    $current->addDay();
                }
            });

        $today = now()->toDateString();

        Attendance::query()
            ->with('employee:id,employee_code,name')
            ->when($employeeId, fn ($query) => $query->where('employee_id', $employeeId))
            ->whereDate('attendance_date', '>=', $start)
            ->whereDate('attendance_date', '<=', $end)
            ->get()
            ->each(function (Attendance $attendance) use (&$eventsByDate, $today) {
                $attendanceDate = $attendance->attendance_date?->toDateString();

                // Jangan tampilkan Alpha untuk hari yang belum berjalan
                if ($attendance->status === Attendance::STATUS_ABSENT && $attendanceDate > $today) {
                    return;
                }

                $color = match ($attendance->status) {
                    Attendance::STATUS_PRESENT => '#50cd89',
                    Attendance::STATUS_LATE => '#ffc700',
                    Attendance::STATUS_ABSENT => '#f1416c',
                    Attendance::STATUS_INCOMPLETE => '#7239ea',
                    Attendance::STATUS_DAY_OFF => '#a1a5b7',
                    Attendance::STATUS_HOLIDAY => '#f1416c',
                    Attendance::STATUS_LEAVE => '#ffc700',
                    default => '#7e8299',
                };
                $label = match ($attendance->status) {
                    Attendance::STATUS_PRESENT => 'Hadir',
                    Attendance::STATUS_LATE => 'Terlambat',
                    Attendance::STATUS_ABSENT => 'Alpha',
                    Attendance::STATUS_INCOMPLETE => 'Tidak Lengkap',
                    Attendance::STATUS_DAY_OFF => 'Libur',
                    Attendance::STATUS_HOLIDAY => 'Libur Perusahaan',
                    Attendance::STATUS_LEAVE => 'Cuti/Izin',
                    default => $attendance->status,
                };

                $checkInStr  = $attendance->check_in_at  ? 'Masuk: '.$attendance->check_in_at->format('H:i')  : null;
                $checkOutStr = $attendance->check_out_at ? 'Pulang: '.$attendance->check_out_at->format('H:i') : null;

                $this->addCalendarSummary($eventsByDate, $attendanceDate, 'attendance_'.$attendance->status, [
                    'label' => $label,
                    'color' => $color,
                    'textColor' => in_array($attendance->status, [Attendance::STATUS_LATE, Attendance::STATUS_LEAVE], true) ? '#181c32' : '#ffffff',
                    'detail' => trim(implode(' | ', array_filter([
                        $attendance->employee ? "{$attendance->employee->employee_code} - {$attendance->employee->name}" : null,
                        $checkInStr,
                        $checkOutStr,
                        $attendance->late_minutes > 0 ? "telat {$attendance->late_minutes} menit" : null,
                        $attendance->note ? $attendance->note : null,
                    ]))),
                ]);
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
        $schedule = EmployeeSchedule::query()->updateOrCreate(
            [
                'employee_id' => $validated['employee_id'],
                'schedule_date' => $validated['schedule_date'],
            ],
            $validated
        );

        app(AttendanceProcessor::class)->rebuildDailyAttendance($schedule->employee, $schedule->schedule_date);

        return response()->json(['message' => 'Jadwal karyawan berhasil disimpan', 'schedule' => $schedule]);
    }

    public function updateSchedule(Request $request, EmployeeSchedule $schedule)
    {
        $oldEmployee = $schedule->employee;
        $oldDate = $schedule->schedule_date;
        $validated = $this->validateSchedule($request);

        $schedule->update($validated);
        $schedule->refresh();

        if ($oldEmployee) {
            app(AttendanceProcessor::class)->rebuildDailyAttendance($oldEmployee, $oldDate);
        }
        app(AttendanceProcessor::class)->rebuildDailyAttendance($schedule->employee, $schedule->schedule_date);

        return response()->json(['message' => 'Jadwal karyawan berhasil diperbarui', 'schedule' => $schedule]);
    }

    public function destroySchedule(EmployeeSchedule $schedule)
    {
        $employee = $schedule->employee;
        $date = $schedule->schedule_date;
        $schedule->delete();
        app(AttendanceProcessor::class)->rebuildDailyAttendance($employee, $date);

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

        return response()->json(['message' => 'Hari libur berhasil dibuat', 'holiday' => $holiday]);
    }

    public function updateHoliday(Request $request, Holiday $holiday)
    {
        $validated = $request->validate([
            'holiday_date' => ['required', 'date', Rule::unique('holidays', 'holiday_date')->ignore($holiday->id)],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', 'string', 'max:30'],
            'is_paid' => ['nullable', 'boolean'],
        ]);
        $validated['is_paid'] = $request->boolean('is_paid');
        $holiday->update($validated);

        return response()->json(['message' => 'Hari libur berhasil diperbarui', 'holiday' => $holiday]);
    }

    public function destroyHoliday(Holiday $holiday)
    {
        $holiday->delete();

        return response()->json(['message' => 'Hari libur berhasil dihapus']);
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
        });

        return response()->json(['message' => 'Template jadwal berhasil diperbarui']);
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
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'weekly_schedule_template_id' => ['required', 'integer', 'exists:weekly_schedule_templates,id'],
            'effective_from' => ['required', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ]);

        $from = Carbon::parse($validated['effective_from'])->startOfDay();
        $until = !empty($validated['effective_until'])
            ? Carbon::parse($validated['effective_until'])->startOfDay()
            : $from->copy()->endOfMonth();

        if ($from->diffInDays($until) > 366) {
            return response()->json([
                'message' => 'Rentang penerapan template maksimal 366 hari.',
                'errors' => ['effective_until' => ['Rentang penerapan template maksimal 366 hari.']],
            ], 422);
        }

        $assignment = DB::transaction(function () use ($validated, $from, $until) {
            $assignment = EmployeeScheduleAssignment::create($validated);
            $this->materializeTemplateSchedules($assignment, $from, $until);

            return $assignment;
        });

        return response()->json([
            'message' => 'Template jadwal berhasil ditetapkan ke karyawan dan jadwal sudah dibuat.',
            'assignment' => $assignment,
            'generated_until' => $until->toDateString(),
        ]);
    }

    public function leavesData(Request $request)
    {
        $query = EmployeeLeave::query()
            ->with('employee:id,employee_code,name')
            ->latest('start_date');

        return $this->datatable($query, $request, fn (EmployeeLeave $leave) => [
            'id' => $leave->id,
            'employee_id' => $leave->employee_id,
            'employee' => $leave->employee ? "{$leave->employee->employee_code} - {$leave->employee->name}" : '-',
            'leave_type' => $leave->leave_type,
            'start_date' => $leave->start_date?->format('Y-m-d'),
            'end_date' => $leave->end_date?->format('Y-m-d'),
            'reason' => $leave->reason,
            'status' => $leave->status,
        ]);
    }

    public function storeLeave(Request $request)
    {
        $validated = $this->validateLeave($request);

        $leave = EmployeeLeave::create($validated + [
            'approved_by' => $validated['status'] === 'approved' ? auth()->id() : null,
            'approved_at' => $validated['status'] === 'approved' ? now() : null,
        ]);
        $this->rebuildAttendanceRange($leave->employee, $leave->start_date, $leave->end_date);

        return response()->json(['message' => 'Cuti/izin berhasil dibuat', 'leave' => $leave]);
    }

    public function updateLeave(Request $request, EmployeeLeave $leave)
    {
        $oldEmployee = $leave->employee;
        $oldStart = $leave->start_date;
        $oldEnd = $leave->end_date;
        $validated = $this->validateLeave($request);

        $leave->update($validated + [
            'approved_by' => $validated['status'] === 'approved' ? auth()->id() : null,
            'approved_at' => $validated['status'] === 'approved' ? now() : null,
        ]);
        $leave->refresh();

        if ($oldEmployee) {
            $this->rebuildAttendanceRange($oldEmployee, $oldStart, $oldEnd);
        }
        $this->rebuildAttendanceRange($leave->employee, $leave->start_date, $leave->end_date);

        return response()->json(['message' => 'Cuti/izin berhasil diperbarui', 'leave' => $leave]);
    }

    public function destroyLeave(EmployeeLeave $leave)
    {
        $employee = $leave->employee;
        $start = $leave->start_date;
        $end = $leave->end_date;
        $leave->delete();

        if ($employee) {
            $this->rebuildAttendanceRange($employee, $start, $end);
        }

        return response()->json(['message' => 'Cuti/izin berhasil dihapus']);
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
        $validated = $request->validate([
            'attendance_device_id' => ['required', 'integer', 'exists:attendance_devices,id'],
            'device_user_id' => ['required', 'string', 'max:100'],
            'scan_at' => ['required', 'date'],
            'verify_type' => ['nullable', 'string', 'max:50'],
            'state' => ['nullable', 'string', 'max:50'],
        ]);

        $oldEmployee = $rawLog->employee;
        $oldDate = $rawLog->scan_at?->toDateString();
        $employeeId = EmployeeFingerprint::query()
            ->where('attendance_device_id', $validated['attendance_device_id'])
            ->where('device_user_id', $validated['device_user_id'])
            ->where('is_active', true)
            ->value('employee_id');

        $rawLog->update($validated + [
            'employee_id' => $employeeId,
            'scan_at' => Carbon::parse($validated['scan_at']),
        ]);
        $rawLog->refresh();

        if ($oldEmployee && $oldDate) {
            app(AttendanceProcessor::class)->rebuildDailyAttendance($oldEmployee, $oldDate);
        }
        if ($rawLog->employee) {
            app(AttendanceProcessor::class)->rebuildDailyAttendance($rawLog->employee, $rawLog->scan_at);
        }

        return response()->json(['message' => 'Raw log fingerprint berhasil diperbarui', 'raw_log' => $rawLog]);
    }

    public function destroyRawLog(AttendanceRawLog $rawLog)
    {
        $employee = $rawLog->employee;
        $date = $rawLog->scan_at?->toDateString();
        $rawLog->delete();

        if ($employee && $date) {
            app(AttendanceProcessor::class)->rebuildDailyAttendance($employee, $date);
        }

        return response()->json(['message' => 'Raw log fingerprint berhasil dihapus']);
    }

    public function attendancesData(Request $request)
    {
        $query = Attendance::query()
            ->with(['employee:id,employee_code,name', 'shift:id,name'])
            ->when($request->input('date_from'), fn ($q, $date) => $q->whereDate('attendance_date', '>=', $date))
            ->when($request->input('date_to'), fn ($q, $date) => $q->whereDate('attendance_date', '<=', $date))
            ->latest('attendance_date');

        return $this->datatable($query, $request, fn (Attendance $attendance) => [
            'id' => $attendance->id,
            'employee_id' => $attendance->employee_id,
            'work_shift_id' => $attendance->work_shift_id,
            'employee' => $attendance->employee ? "{$attendance->employee->employee_code} - {$attendance->employee->name}" : '-',
            'attendance_date' => $attendance->attendance_date?->format('Y-m-d'),
            'shift' => $attendance->shift?->name ?? '-',
            'check_in_at' => $attendance->check_in_at?->format('Y-m-d H:i:s'),
            'check_out_at' => $attendance->check_out_at?->format('Y-m-d H:i:s'),
            'late_minutes' => $attendance->late_minutes,
            'early_leave_minutes' => $attendance->early_leave_minutes,
            'work_minutes' => $attendance->work_minutes,
            'overtime_minutes' => $attendance->overtime_minutes,
            'status' => $attendance->status,
            'source' => $attendance->source,
            'note' => $attendance->note,
        ]);
    }

    public function updateAttendance(Request $request, Attendance $attendance)
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'attendance_date' => ['required', 'date'],
            'work_shift_id' => ['nullable', 'integer', 'exists:work_shifts,id'],
            'check_in_at' => ['nullable', 'date'],
            'check_out_at' => ['nullable', 'date'],
            'late_minutes' => ['required', 'integer', 'min:0'],
            'early_leave_minutes' => ['required', 'integer', 'min:0'],
            'work_minutes' => ['required', 'integer', 'min:0'],
            'overtime_minutes' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'string', 'max:30'],
            'source' => ['nullable', 'string', 'max:30'],
            'note' => ['nullable', 'string'],
        ]);

        $attendance->update($validated + [
            'source' => $validated['source'] ?? 'manual',
        ]);

        return response()->json(['message' => 'Rekap absensi berhasil diperbarui', 'attendance' => $attendance]);
    }

    public function destroyAttendance(Attendance $attendance)
    {
        $attendance->delete();

        return response()->json(['message' => 'Rekap absensi berhasil dihapus']);
    }

    private function validateEmployee(Request $request, ?Employee $employee = null): array
    {
        return $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id', Rule::unique('employees', 'user_id')->ignore($employee?->id)],
            'area_id' => ['nullable', 'integer', 'exists:areas,id'],
            'position_id' => ['nullable', 'integer', 'exists:employee_positions,id'],
            'employee_code' => ['required', 'string', 'max:50', Rule::unique('employees', 'employee_code')->ignore($employee?->id)],
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
            'position' => ['nullable', 'string', 'max:100'],
            'join_date' => ['nullable', 'date'],
            'employment_status' => ['required', 'string', 'max:30'],
        ]);
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
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
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
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'work_shift_id' => ['nullable', 'integer', 'exists:work_shifts,id'],
            'schedule_date' => ['required', 'date'],
            'schedule_type' => ['required', 'string', 'max:30'],
            'note' => ['nullable', 'string'],
        ]);
    }

    private function validateLeave(Request $request): array
    {
        return $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'leave_type' => ['required', 'string', 'max:30'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string'],
            'status' => ['required', 'string', 'max:30'],
        ]);
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

    private function syncTemplateDays(WeeklyScheduleTemplate $template, array $days): void
    {
        $template->days()->delete();
        foreach ($days as $day) {
            WeeklyScheduleTemplateDay::create([
                'weekly_schedule_template_id' => $template->id,
                'day_of_week' => $day['day_of_week'],
                'schedule_type' => $day['schedule_type'],
                'work_shift_id' => ($day['schedule_type'] ?? '') === EmployeeSchedule::TYPE_WORK ? ($day['work_shift_id'] ?? null) : null,
            ]);
        }
    }

    private function materializeTemplateSchedules(EmployeeScheduleAssignment $assignment, Carbon $from, Carbon $until): void
    {
        $templateDays = WeeklyScheduleTemplateDay::query()
            ->where('weekly_schedule_template_id', $assignment->weekly_schedule_template_id)
            ->get()
            ->keyBy('day_of_week');

        $employee = Employee::findOrFail($assignment->employee_id);
        $current = $from->copy();

        while ($current->lte($until)) {
            $templateDay = $templateDays->get((int) $current->dayOfWeekIso);

            if ($templateDay) {
                $schedule = EmployeeSchedule::query()->updateOrCreate(
                    [
                        'employee_id' => $assignment->employee_id,
                        'schedule_date' => $current->toDateString(),
                    ],
                    [
                        'work_shift_id' => $templateDay->schedule_type === EmployeeSchedule::TYPE_WORK ? $templateDay->work_shift_id : null,
                        'schedule_type' => $templateDay->schedule_type,
                        'note' => 'Dibuat dari template jadwal',
                        'created_by' => auth()->id(),
                    ]
                );

                app(AttendanceProcessor::class)->rebuildDailyAttendance($employee, $schedule->schedule_date);
            }

            $current->addDay();
        }
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
}
