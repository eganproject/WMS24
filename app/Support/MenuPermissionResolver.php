<?php

namespace App\Support;

use App\Models\Menu;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class MenuPermissionResolver
{
    /** @var Collection<int, Menu>|null */
    protected ?Collection $menus = null;

    /** @var array<int, Collection<int, Permission>> */
    protected array $permissionsByJabatan = [];

    public function userCan(string $ability, ?string $routeName = null, ?string $path = null): bool
    {
        if (!Auth::check()) {
            return false;
        }

        $menu = $this->resolveMenu($routeName, $path);
        if (!$menu) {
            return false;
        }

        $column = $this->abilityToColumn($ability);
        $permissions = $this->permissionsForJabatan((int) Auth::user()->jabatan_id);
        $permission = $permissions->get($menu->id);

        if (!$permission) {
            return false;
        }

        return (bool) data_get($permission, $column, false);
    }

    public function userCanForRequest(Request $request): bool
    {
        if (!Auth::check()) {
            return false;
        }

        $routeName = $request->route()->getName();
        $ability = $this->resolveAbility($request->method(), $routeName);
        $menu = $this->resolveMenu($routeName, $request->path());

        if (!$menu) {
            return true;
        }

        $column = $this->abilityToColumn($ability);
        $permissions = $this->permissionsForJabatan((int) Auth::user()->jabatan_id);
        $permission = $permissions->get($menu->id);

        if (!$permission) {
            return false;
        }

        return (bool) data_get($permission, $column, false);
    }

    public function resolveAbility(string $method, ?string $routeName): string
    {
        $routeName = $routeName ?? '';
        $method = strtoupper($method);

        if (Str::contains($routeName, ['approve', 'updateStatus', 'set-status']) || Str::contains(strtolower($routeName), 'approve')) {
            return 'approve';
        }

        if (Str::contains($routeName, ['.destroy']) || $method === 'DELETE') {
            return 'delete';
        }

        if (Str::contains($routeName, ['.edit', '.update']) || in_array($method, ['PUT', 'PATCH'], true)) {
            return 'edit';
        }

        if (Str::contains($routeName, ['.create', '.store']) || $method === 'POST') {
            return 'create';
        }

        return 'read';
    }

    public function resolveMenu(?string $routeName = null, ?string $path = null): ?Menu
    {
        $path = '/' . ltrim($path ?? request()->path(), '/');
        $menus = $this->getMenus();

        return $menus
            ->filter(function (Menu $menu) use ($path) {
                if ($menu->normalized_url === '' || $menu->normalized_url === '/') {
                    return false;
                }

                return Str::startsWith($path, $menu->normalized_url);
            })
            ->sortByDesc(fn (Menu $menu) => strlen($menu->normalized_url))
            ->first();
    }

    protected function abilityToColumn(string $ability): string
    {
        return match (strtolower($ability)) {
            'create' => 'can_create',
            'edit', 'update' => 'can_edit',
            'delete', 'destroy' => 'can_delete',
            'approve' => 'can_approve',
            default => 'can_read',
        };
    }

    /**
     * @return Collection<int, Menu>
     */
    protected function getMenus(): Collection
    {
        if ($this->menus === null) {
            $this->menus = Menu::query()
                ->select(['id', 'url'])
                ->whereNotNull('url')
                ->get()
                ->map(function (Menu $menu) {
                    $menu->normalized_url = '/' . ltrim($menu->url ?? '', '/');
                    return $menu;
                });
        }

        return $this->menus;
    }

    /**
     * @return Collection<int, Permission>
     */
    protected function permissionsForJabatan(int $jabatanId): Collection
    {
        if (!isset($this->permissionsByJabatan[$jabatanId])) {
            $this->permissionsByJabatan[$jabatanId] = Permission::where('jabatan_id', $jabatanId)
                ->get()
                ->keyBy('menu_id');
        }

        return $this->permissionsByJabatan[$jabatanId];
    }
}
