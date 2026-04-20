<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\QcScanException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class QcScanExceptionController extends Controller
{
    public function index()
    {
        return view('admin.outbound.qc-scan-exceptions.index');
    }

    public function data(Request $request)
    {
        $query = QcScanException::query()->orderBy('sku');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('sku', 'like', "%{$search}%")
                    ->orWhere('note', 'like', "%{$search}%");
            });
        }

        $recordsTotal = QcScanException::count();
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $data = $query->get()->map(function ($row) {
            return [
                'id' => $row->id,
                'sku' => $row->sku,
                'note' => $row->note ?? '-',
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
        $validated = $request->validate([
            'sku' => ['required', 'string', 'max:100', 'unique:qc_scan_exceptions,sku'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $validated['sku'] = strtoupper(trim($validated['sku']));

        DB::beginTransaction();
        try {
            $row = QcScanException::create($validated);
            DB::commit();

            return response()->json([
                'message' => 'SKU exception QC berhasil ditambahkan',
                'exception' => [
                    'id' => $row->id,
                    'sku' => $row->sku,
                    'note' => $row->note,
                ],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Gagal menambahkan SKU exception QC',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, QcScanException $exception)
    {
        $validated = $request->validate([
            'sku' => ['required', 'string', 'max:100', Rule::unique('qc_scan_exceptions', 'sku')->ignore($exception->id)],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $validated['sku'] = strtoupper(trim($validated['sku']));

        DB::beginTransaction();
        try {
            $exception->update($validated);
            DB::commit();

            return response()->json([
                'message' => 'SKU exception QC berhasil diperbarui',
                'exception' => [
                    'id' => $exception->id,
                    'sku' => $exception->sku,
                    'note' => $exception->note,
                ],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Gagal memperbarui SKU exception QC',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(QcScanException $exception)
    {
        DB::beginTransaction();
        try {
            $exception->delete();
            DB::commit();

            return response()->json(['message' => 'SKU exception QC berhasil dihapus']);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Gagal menghapus SKU exception QC',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
