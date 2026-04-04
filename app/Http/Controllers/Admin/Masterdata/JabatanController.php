<?php

namespace App\Http\Controllers\Admin\Masterdata;

use App\Http\Controllers\Controller;
use App\Models\Jabatan;
use App\Models\UserActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class JabatanController extends Controller
{
    public function index()
    {
        $jabatans = Jabatan::all();
        return view('admin.masterdata.jabatans.index', compact('jabatans'));
    }

    public function create()
    {
        return view('admin.masterdata.jabatans.create');
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
            ]);

            $jabatan = Jabatan::create($request->all());

            UserActivity::create([
                'user_id' => Auth::id(),
                'activity' => 'created',
                'menu' => 'jabatans',
                'description' => 'Menambahkan jabatan baru: ' . $jabatan->name,
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
            ]);

            return redirect()->route('admin.masterdata.jabatans.index')->with('success', 'Jabatan berhasil ditambahkan.');
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', 'Gagal menambahkan jabatan: ' . $e->getMessage());
        }
    }

    public function edit(Jabatan $jabatan)
    {
        return view('admin.masterdata.jabatans.edit', compact('jabatan'));
    }

    public function update(Request $request, Jabatan $jabatan)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
            ]);

            $jabatan->update($request->all());

            UserActivity::create([
                'user_id' => Auth::id(),
                'activity' => 'updated',
                'menu' => 'jabatans',
                'description' => 'Memperbarui jabatan: ' . $jabatan->name,
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
            ]);

            return redirect()->route('admin.masterdata.jabatans.index')->with('success', 'Jabatan berhasil diperbarui.');
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', 'Gagal memperbarui jabatan: ' . $e->getMessage());
        }
    }

    public function destroy(Request $request, Jabatan $jabatan)
    {
        try {
            $jabatanName = $jabatan->name;
            $jabatan->delete();

            UserActivity::create([
                'user_id' => Auth::id(),
                'activity' => 'deleted',
                'menu' => 'jabatans',
                'description' => 'Menghapus jabatan: ' . $jabatanName,
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
            ]);

            return redirect()->route('admin.masterdata.jabatans.index')->with('success', 'Jabatan berhasil dihapus.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal menghapus jabatan: ' . $e->getMessage());
        }
    }
}