<?php

namespace App\Http\View\Composers;

use Illuminate\View\View;
use App\Models\Menu;
use App\Models\Permission;
use Illuminate\Support\Facades\Auth;

class MenuComposer
{
    public function compose(View $view)
    {
        $user = Auth::user();
        $composedMenus = collect();

        if ($user && $user->jabatan) {
            $jabatanId = $user->jabatan->id;

            $allMenus = Menu::with('children')->where('is_active', true)->orderBy('order')->get();

            foreach ($allMenus as $menu) {
                // Check if the user has 'read' permission for the main menu
                $hasReadPermission = Permission::where('jabatan_id', $jabatanId)
                                            ->where('menu_id', $menu->id)
                                            ->where('permission_type', 'read')
                                            ->exists();

                if ($hasReadPermission) {
                    $menuPermissions = Permission::where('jabatan_id', $jabatanId)
                                                ->where('menu_id', $menu->id)
                                                ->pluck('permission_type')
                                                ->toArray();
                    $menu->user_permissions = $menuPermissions;

                    $composedMenus->push($menu);
                }
            }
        }

        $view->with('composedMenus', $composedMenus);
    }
}