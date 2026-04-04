<?php

namespace App\Http\Controllers\Admin\Masterdata;

use App\Http\Controllers\Controller;
use App\Models\Jabatan;
use App\Models\Menu;
use App\Models\Permission;
use App\Models\UserActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PermissionController extends Controller
{
    public function index()
    {
        $jabatans = Jabatan::all();
        return view('admin.masterdata.permissions.index', compact('jabatans'));
    }

    public function getPermissionsByJabatan(Request $request)
    {
        $jabatanId = $request->input('jabatan_id');
        $menus = Menu::with('children')->whereNull('parent_id')->orderBy('order')->get();
        $permissions = Permission::where('jabatan_id', $jabatanId)->get()->keyBy(function($item) {
            return $item->jabatan_id . '-' . $item->menu_id;
        });
        $jabatans = Jabatan::where('id', $jabatanId)->get();

        return response()->json([
            'menus' => $menus,
            'permissions' => $permissions,
            'jabatans' => $jabatans
        ]);
    }

    public function update(Request $request)
    {
        try {
            DB::beginTransaction();

            $jabatanId = $request->input('jabatan_id');
            $menuId = $request->input('menu_id');
            $permissionType = $request->input('permission_type'); // e.g., 'can_read', 'can_create'
            $isChecked = filter_var($request->input('is_checked'), FILTER_VALIDATE_BOOLEAN);

            $permission = Permission::firstOrNew([
                'jabatan_id' => $jabatanId,
                'menu_id' => $menuId,
            ]);

            $oldValue = $permission->$permissionType;
            $permission->$permissionType = $isChecked;
            $permission->save();

            $activityDescription = '';
            if ($isChecked) {
                $activityDescription = 'Memberikan izin ' . str_replace('can_', '', $permissionType) . ' untuk jabatan ' . Jabatan::find($jabatanId)->name . ' pada menu ' . Menu::find($menuId)->name;
            } else {
                $activityDescription = 'Mencabut izin ' . str_replace('can_', '', $permissionType) . ' dari jabatan ' . Jabatan::find($jabatanId)->name . ' pada menu ' . Menu::find($menuId)->name;
            }

            UserActivity::create([
                'user_id' => Auth::id(),
                'activity' => 'updated',
                'menu' => 'permissions',
                'description' => $activityDescription,
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
            ]);

            DB::commit();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal memperbarui izin: ' . $e->getMessage()], 500);
        }
    }
}