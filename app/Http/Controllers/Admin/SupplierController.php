<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SupplierController extends Controller
{
    public function index()
    {
        return view('admin.masterdata.suppliers.index');
    }

    public function data(Request $request)
    {
        $query = Supplier::orderBy('name');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where('name', 'like', "%{$search}%");
        }

        $recordsTotal = Supplier::count();
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $data = $query->get()->map(function (Supplier $supplier) {
            return [
                'id' => $supplier->id,
                'name' => $supplier->name,
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function store(Request $request)
    {
        $request->merge([
            'name' => $this->normalizeName((string) $request->input('name')),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150', 'unique:suppliers,name'],
        ]);

        DB::beginTransaction();
        try {
            $supplier = Supplier::create($validated);
            DB::commit();

            return response()->json([
                'message' => 'Supplier berhasil dibuat',
                'supplier' => [
                    'id' => $supplier->id,
                    'name' => $supplier->name,
                ],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Gagal membuat supplier',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, Supplier $supplier)
    {
        $request->merge([
            'name' => $this->normalizeName((string) $request->input('name')),
        ]);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('suppliers', 'name')->ignore($supplier->id),
            ],
        ]);

        DB::beginTransaction();
        try {
            $supplier->update($validated);
            DB::commit();

            return response()->json([
                'message' => 'Supplier berhasil diperbarui',
                'supplier' => [
                    'id' => $supplier->id,
                    'name' => $supplier->name,
                ],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Gagal memperbarui supplier',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Supplier $supplier)
    {
        $isUsed = $supplier->inboundTransactions()->exists() || $supplier->outboundTransactions()->exists();
        if ($isUsed) {
            return response()->json([
                'message' => 'Supplier sudah dipakai transaksi dan tidak bisa dihapus.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $supplier->delete();
            DB::commit();

            return response()->json([
                'message' => 'Supplier berhasil dihapus',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Gagal menghapus supplier',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function normalizeName(string $name): string
    {
        $name = trim($name);
        return preg_replace('/\s+/', ' ', $name) ?? $name;
    }
}
