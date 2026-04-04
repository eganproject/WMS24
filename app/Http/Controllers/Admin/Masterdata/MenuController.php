<?php

namespace App\Http\Controllers\Admin\Masterdata;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\UserActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MenuController extends Controller
{
    public function index()
    {
        $menus = Menu::with('parent')->orderBy('order')->get();
        return view('admin.masterdata.menus.index', compact('menus'));
    }

    public function create()
    {
        $parentMenus = Menu::whereNull('parent_id')->get();
        return view('admin.masterdata.menus.create', compact('parentMenus'));
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'url' => 'nullable|string|max:255',
                'icon' => 'nullable|string|max:255',
                'parent_id' => 'nullable|exists:menus,id',
                'order' => 'required|integer',
                'is_active' => 'boolean',
            ]);

            $menu = Menu::create($request->all());

            UserActivity::create([
                'user_id' => Auth::id(),
                'activity' => 'created',
                'menu' => 'menus',
                'description' => 'Menambahkan menu baru: ' . $menu->name,
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
            ]);

            return redirect()->route('admin.masterdata.menus.index')->with('success', 'Menu berhasil ditambahkan.');
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', 'Gagal menambahkan menu: ' . $e->getMessage());
        }
    }

    public function edit(Menu $menu)
    {
        $parentMenus = Menu::whereNull('parent_id')->where('id', '!=', $menu->id)->get();
        return view('admin.masterdata.menus.edit', compact('menu', 'parentMenus'));
    }

    public function update(Request $request, Menu $menu)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'url' => 'nullable|string|max:255',
                'icon' => 'nullable|string|max:255',
                'parent_id' => 'nullable|exists:menus,id',
                'order' => 'required|integer',
                'is_active' => 'boolean',
            ]);

            $menu->update($request->all());

            UserActivity::create([
                'user_id' => Auth::id(),
                'activity' => 'updated',
                'menu' => 'menus',
                'description' => 'Memperbarui menu: ' . $menu->name,
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
            ]);

            return redirect()->route('admin.masterdata.menus.index')->with('success', 'Menu berhasil diperbarui.');
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error' , 'Gagal memperbarui menu: ' . $e->getMessage());
        }
    }

    public function destroy(Request $request, Menu $menu)
    {
        try {
            $menuName = $menu->name;
            $menu->delete();

            UserActivity::create([
                'user_id' => Auth::id(),
                'activity' => 'deleted',
                'menu' => 'menus',
                'description' => 'Menghapus menu: ' . $menuName,
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
            ]);

            return redirect()->route('admin.masterdata.menus.index')->with('success', 'Menu berhasil dihapus.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal menghapus menu: ' . $e->getMessage());
        }
    }
}