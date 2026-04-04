<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class UserActivityController extends Controller
{
    public function index(Request $request)
    {
        $currentUser = $request->user();
        $forcedUserId = $currentUser && $currentUser->warehouse_id ? $currentUser->id : null;
        $canFilterUsers = $forcedUserId === null;

        if ($request->ajax()) {
            return $this->datatableResponse($request);
        }

        $search = trim((string) $request->input('search', ''));
        $startDate = $request->input('start_date', '');
        $endDate = $request->input('end_date', '');
        $selectedUserId = $forcedUserId ?? $request->input('user_id');

        $users = $canFilterUsers
            ? User::orderBy('name')->get(['id', 'name', 'email'])
            : collect();

        $defaultUserText = $forcedUserId
            ? trim($currentUser->name . ($currentUser->email ? ' - ' . $currentUser->email : ''))
            : null;

        return view('admin.user_activity.index', [
            'users' => $users,
            'search' => $search,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'selectedUserId' => $selectedUserId,
            'canFilterUsers' => $canFilterUsers,
            'defaultUserId' => $forcedUserId,
            'defaultUserText' => $defaultUserText,
        ]);
    }

    private function datatableResponse(Request $request)
    {
        $currentUser = $request->user();
        $forcedUserId = $currentUser && $currentUser->warehouse_id ? $currentUser->id : null;

        $searchValue = trim((string) $request->input('search.value', ''));
        $start = max((int) $request->input('start', 0), 0);
        $length = (int) $request->input('length', 10);
        $userIdFilter = $forcedUserId ?? $request->input('user_id');
        $startDate = $this->parseDate($request->input('start_date'));
        $endDate = $this->parseDate($request->input('end_date'));

        if ($startDate && $endDate && $endDate->lessThan($startDate)) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        $baseQuery = UserActivity::query()
            ->when($forcedUserId, function ($query) use ($forcedUserId) {
                $query->where('user_id', $forcedUserId);
            });

        $totalRecords = (clone $baseQuery)->count();

        $query = (clone $baseQuery)->with('user')
            ->when($userIdFilter, function ($query) use ($userIdFilter) {
                $query->where('user_id', $userIdFilter);
            })
            ->when($startDate, function ($query) use ($startDate) {
                $query->where('created_at', '>=', $startDate->copy()->startOfDay());
            })
            ->when($endDate, function ($query) use ($endDate) {
                $query->where('created_at', '<=', $endDate->copy()->endOfDay());
            });

        $filteredQuery = clone $query;

        if ($searchValue !== '') {
            $filteredQuery->where(function ($innerQuery) use ($searchValue) {
                $innerQuery->where('activity', 'like', "%{$searchValue}%")
                    ->orWhere('menu', 'like', "%{$searchValue}%")
                    ->orWhere('description', 'like', "%{$searchValue}%")
                    ->orWhere('ip_address', 'like', "%{$searchValue}%")
                    ->orWhere('user_agent', 'like', "%{$searchValue}%")
                    ->orWhereHas('user', function ($userQuery) use ($searchValue) {
                        $userQuery->where('name', 'like', "%{$searchValue}%")
                            ->orWhere('email', 'like', "%{$searchValue}%");
                    });
            });
        }

        $recordsFiltered = $filteredQuery->count();

        $filteredQuery->orderByDesc('created_at');

        if ($length > 0) {
            $filteredQuery->skip($start)->take($length);
        }

        $activities = $filteredQuery->get();

        $data = $activities->map(function (UserActivity $activity) {
            $createdAt = $activity->created_at;
            $createdAtDisplay = $createdAt ? $createdAt->format('d/m/Y H:i') : '-';

            return [
                'user_name' => $activity->user->name ?? 'Tidak diketahui',
                'user_email' => $activity->user->email ?? '-',
                'activity' => $activity->activity,
                'menu' => $activity->menu ?? '-',
                'description' => $activity->description ?? '-',
                'ip_address' => $activity->ip_address ?? '-',
                'user_agent' => $activity->user_agent ?? '',
                'user_agent_short' => Str::limit($activity->user_agent ?? '-', 60),
                'created_at_display' => $createdAtDisplay,
                'created_at' => $createdAt ? $createdAt->toIso8601String() : null,
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw', 0),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    private function parseDate(?string $date): ?Carbon
    {
        if (!$date) {
            return null;
        }

        try {
            return Carbon::parse($date);
        } catch (\Throwable $th) {
            return null;
        }
    }
}
