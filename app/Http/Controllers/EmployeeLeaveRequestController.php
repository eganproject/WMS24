<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeLeave;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EmployeeLeaveRequestController extends Controller
{
    public function index(Request $request)
    {
        $employee = $this->employeeForUser($request);

        $leaves = $employee
            ? EmployeeLeave::query()
                ->where('employee_id', $employee->id)
                ->latest('start_date')
                ->latest('id')
                ->get()
            : collect();

        return view('employee.leave-requests', [
            'employee' => $employee,
            'leaves' => $leaves,
            'storeUrl' => route('employee.leave-requests.store'),
            'proofUrlTpl' => route('employee.leave-requests.proof-image', ':id'),
            'dashboardUrl' => route('mobile.dashboard'),
        ]);
    }

    public function store(Request $request)
    {
        $employee = $this->employeeForUser($request);
        if (!$employee) {
            throw ValidationException::withMessages([
                'employee' => ['Akun login belum terhubung ke data karyawan. Hubungi HR/Admin.'],
            ]);
        }

        $validated = $request->validate([
            'leave_type' => ['required', 'string', 'max:30'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string'],
            'proof_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $this->ensureNoOverlap($employee, $validated['start_date'], $validated['end_date']);

        $storedProofImage = null;

        try {
            if ($request->hasFile('proof_image')) {
                $storedProofImage = $request->file('proof_image')->store('employee-leave-proofs', 'public');
                $validated['proof_image_path'] = 'storage/'.$storedProofImage;
            }

            $leave = EmployeeLeave::create([
                'employee_id' => $employee->id,
                'leave_type' => $validated['leave_type'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'reason' => $validated['reason'],
                'proof_image_path' => $validated['proof_image_path'] ?? null,
                'submitted_by_user_id' => $request->user()->id,
                'submitted_at' => now(),
                'submission_source' => 'employee_self_service',
                'status' => EmployeeLeave::STATUS_PENDING,
                'approved_by' => null,
                'approved_at' => null,
            ]);
        } catch (\Throwable $e) {
            if ($storedProofImage) {
                Storage::disk('public')->delete($storedProofImage);
            }

            throw $e;
        }

        return redirect()
            ->route('employee.leave-requests.index')
            ->with('success', 'Pengajuan cuti/izin berhasil dikirim dan menunggu approval.');
    }

    public function proofImage(Request $request, EmployeeLeave $leave)
    {
        $employee = $this->employeeForUser($request);
        abort_if(!$employee || (int) $leave->employee_id !== (int) $employee->id, 403);

        $path = $this->storageRelativePath($leave->proof_image_path);
        abort_if(!$path || !Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->response($path, null, [
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    private function employeeForUser(Request $request): ?Employee
    {
        return $request->user()?->employee()
            ->with(['area:id,code,name', 'positionRelation:id,name'])
            ->first();
    }

    private function ensureNoOverlap(Employee $employee, string $startDate, string $endDate): void
    {
        $overlapExists = EmployeeLeave::query()
            ->where('employee_id', $employee->id)
            ->whereIn('status', [EmployeeLeave::STATUS_PENDING, EmployeeLeave::STATUS_APPROVED])
            ->whereDate('start_date', '<=', Carbon::parse($endDate)->toDateString())
            ->whereDate('end_date', '>=', Carbon::parse($startDate)->toDateString())
            ->exists();

        if ($overlapExists) {
            throw ValidationException::withMessages([
                'start_date' => ['Anda sudah memiliki cuti/izin pending atau approved pada rentang tanggal tersebut.'],
            ]);
        }
    }

    private function storageRelativePath(?string $path): ?string
    {
        if (!$path || !str_starts_with($path, 'storage/')) {
            return null;
        }

        return Str::after($path, 'storage/');
    }
}
