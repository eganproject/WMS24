<?php

namespace App\Http\Controllers\Admin\Masterdata;

use App\Http\Controllers\Controller;
use App\Models\Uom;
use App\Models\UserActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UomController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $uoms = Uom::all();
        return view('admin.masterdata.uoms.index', compact('uoms'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.masterdata.uoms.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255|unique:uoms',
                'description' => 'nullable|string',
            ]);

            $uom = Uom::create($request->all());

            UserActivity::create([
                'user_id' => Auth::id(),
                'activity' => 'created',
                'menu' => 'uoms',
                'description' => 'Menambahkan UOM baru: ' . $uom->name,
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
            ]);

            return redirect()->route('admin.masterdata.uoms.index')
                             ->with('success', 'UOM created successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', 'Failed to create UOM: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Uom $uom)
    {
        return view('admin.masterdata.uoms.show', compact('uom'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Uom $uom)
    {
        return view('admin.masterdata.uoms.edit', compact('uom'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Uom $uom)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255|unique:uoms,name,' . $uom->id,
                'description' => 'nullable|string',
            ]);

            $uom->update($request->all());

            UserActivity::create([
                'user_id' => Auth::id(),
                'activity' => 'updated',
                'menu' => 'uoms',
                'description' => 'Memperbarui UOM: ' . $uom->name,
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
            ]);

            return redirect()->route('admin.masterdata.uoms.index')
                             ->with('success', 'UOM updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', 'Failed to update UOM: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request,Uom $uom)
    {
        try {
            $uomName = $uom->name;
            $uom->delete();

            UserActivity::create([
                'user_id' => Auth::id(),
                'activity' => 'deleted',
                'menu' => 'uoms',
                'description' => 'Menghapus UOM: ' . $uomName,
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
            ]);

            return redirect()->route('admin.masterdata.uoms.index')
                             ->with('success', 'UOM deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to delete UOM: ' . $e->getMessage()]);
        }
    }
}
