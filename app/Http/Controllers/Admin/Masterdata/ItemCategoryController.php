<?php

namespace App\Http\Controllers\Admin\Masterdata;

use App\Http\Controllers\Controller;
use App\Models\ItemCategory;
use App\Models\UserActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ItemCategoryController extends Controller
{
    public function index()
    {
        $itemcategories = ItemCategory::all();
        return view('admin.masterdata.itemcategories.index', compact('itemcategories'));
    }

    public function create()
    {
        return view('admin.masterdata.itemcategories.create');
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
            ]);

            $itemcategory = ItemCategory::create($request->all());

            UserActivity::create([
                'user_id' => Auth::id(),
                'activity' => 'created',
                'menu' => 'itemcategories',
                'description' => 'Menambahkan kategori item baru: ' . $itemcategory->name,
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
            ]);

            return redirect()->route('admin.masterdata.itemcategories.index')->with('success', 'Kategori item berhasil ditambahkan.');
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', 'Gagal menambahkan kategori item: ' . $e->getMessage());
        }
    }

    public function edit(ItemCategory $itemcategory)
    {
        return view('admin.masterdata.itemcategories.edit', compact('itemcategory'));
    }

    public function update(Request $request, ItemCategory $itemcategory)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
            ]);

            $itemcategory->update($request->all());

            UserActivity::create([
                'user_id' => Auth::id(),
                'activity' => 'updated',
                'menu' => 'itemcategories',
                'description' => 'Memperbarui kategori item: ' . $itemcategory->name,
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
            ]);

            return redirect()->route('admin.masterdata.itemcategories.index')->with('success', 'Kategori item berhasil diperbarui.');
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error' , 'Gagal memperbarui kategori item: ' . $e->getMessage());
        }
    }

    public function destroy(Request $request, ItemCategory $itemcategory)
    {
        try {
            $itemcategoryName = $itemcategory->name;
            $itemcategory->delete();

            UserActivity::create([
                'user_id' => Auth::id(),
                'activity' => 'deleted',
                'menu' => 'itemcategories',
                'description' => 'Menghapus kategori item: ' . $itemcategoryName,
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
            ]);

            return redirect()->route('admin.masterdata.itemcategories.index')->with('success', 'Kategori item berhasil dihapus.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal menghapus kategori item: ' . $e->getMessage());
        }
    }
}