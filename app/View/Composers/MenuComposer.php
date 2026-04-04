<?php

namespace App\View\Composers;

use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;
use App\Models\Menu;
use App\Models\Permission;

class MenuComposer
{
    /**
     * Bind data to the view.
     *
     * @param  \Illuminate\View\View  $view
     * @return void
     */
    public function compose(View $view)
    {
        if (Auth::check()) {
            $user = Auth::user();
            $jabatanId = $user->jabatan_id;

            $allMenus = Menu::where('is_active', true)->orderBy('order')->get();

            $allowedMenuIds = Permission::where('jabatan_id', $jabatanId)
                                        ->where('can_read', true)
                                        ->pluck('menu_id')
                                        ->toArray();

            $filteredMenus = $allMenus->filter(function ($menu) use ($allowedMenuIds) {
                return in_array($menu->id, $allowedMenuIds);
            });

            $menuTree = $this->buildMenuTree($filteredMenus);

            $view->with('menuTree', $menuTree);
        } else {
            $view->with('menuTree', []);
        }
    }

    /**
     * Build a hierarchical menu tree.
     *
     * @param  \Illuminate\Support\Collection  $menus
     * @param  int|null  $parentId
     * @return array
     */
    protected function buildMenuTree($menus, $parentId = null)
    {
        $branch = [];

        foreach ($menus as $menu) {
            if ($menu->parent_id === $parentId) {
                $children = $this->buildMenuTree($menus, $menu->id);

                $menu->children = $children;

                $branch[] = $menu;
            }
        }

        return $branch;
    }
}
