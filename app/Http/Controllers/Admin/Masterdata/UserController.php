<?php

namespace App\Http\Controllers\Admin\Masterdata;

use App\Http\Controllers\Controller;
use App\Models\Jabatan;
use App\Models\User;
use App\Models\UserActivity;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with(['jabatan','warehouse'])->get();
        return view('admin.masterdata.users.index', compact('users'));
    }

    public function create()
    {
        $jabatans = Jabatan::all();
        $warehouses = Warehouse::all();
        return view('admin.masterdata.users.create', compact('jabatans','warehouses'));
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8',
                'jabatan_id' => 'required|exists:jabatans,id',
                'warehouse_id' => 'nullable|exists:warehouses,id',
            ]);

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'jabatan_id' => $request->jabatan_id,
                'warehouse_id' => $request->input('warehouse_id') ?: null,
            ]);

            UserActivity::create([
                'user_id' => Auth::id(),
                'activity' => 'created',
                'menu' => 'users',
                'description' => 'Menambahkan pengguna baru: ' . $user->name,
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
            ]);

            return redirect()->route('admin.masterdata.users.index')->with('success', 'Pengguna berhasil ditambahkan.');
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', 'Gagal menambahkan pengguna: ' . $e->getMessage());
        }
    }

    public function edit(User $user)
    {
        $jabatans = Jabatan::all();
        $warehouses = Warehouse::all();
        return view('admin.masterdata.users.edit', compact('user', 'jabatans', 'warehouses'));
    }

    public function update(Request $request, User $user)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
                'password' => 'nullable|string|min:8',
                'jabatan_id' => 'required|exists:jabatans,id',
                'warehouse_id' => 'nullable|exists:warehouses,id',
            ]);

            $data = $request->except('password');
            // Normalize empty warehouse to null
            $data['warehouse_id'] = $request->input('warehouse_id') ?: null;
            if ($request->filled('password')) {
                $data['password'] = Hash::make($request->password);
            }

            $user->update($data);

            UserActivity::create([
                'user_id' => Auth::id(),
                'activity' => 'updated',
                'menu' => 'users',
                'description' => 'Memperbarui pengguna: ' . $user->name,
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
            ]);

            return redirect()->route('admin.masterdata.users.index')->with('success', 'Pengguna berhasil diperbarui.');
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', 'Gagal memperbarui pengguna: ' . $e->getMessage());
        }
    }

    public function destroy(Request $request, User $user)
    {
        try {
            $userName = $user->name;
            $user->delete();

            UserActivity::create([
                'user_id' => Auth::id(),
                'activity' => 'deleted',
                'menu' => 'users',
                'description' => 'Menghapus pengguna: ' . $userName,
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
            ]);

            return redirect()->route('admin.masterdata.users.index')->with('success', 'Pengguna berhasil dihapus.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal menghapus pengguna: ' . $e->getMessage());
        }
    }
}
