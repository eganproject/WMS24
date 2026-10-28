<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Imports\ResiImport;
use App\Models\Channel;
use App\Models\Item;
use App\Models\Kurir;
use App\Models\PickingList;
use App\Models\PickingListException;
use App\Models\QcResiScan;
use App\Models\Resi;
use App\Models\ResiDetail;
use App\Models\ShipmentScanOut;
use App\Models\StockMutation;
use App\Models\Store;
use App\Support\BundleService;
use App\Support\PickingListBalanceService;
use App\Support\QcTransitStatus;
use App\Support\ResiOperationalStatus;
use App\Support\SameDay;
use App\Support\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class ResiImportController extends Controller
{
    private const IMPORT_LOCK_KEY = 'resi-import:running';
    private const IMPORT_CHUNK_SIZE = 200;
    private const IMPORT_CHUNK_ATTEMPTS = 3;
    private const IMPORT_LOCK_WAIT_SECONDS = 15;

    public function index(Request $request)
    {
        $today = now()->toDateString();
        [$filterDateFrom, $filterDateTo] = $this->resolveUploadDateRange($request);
        $search = trim((string) $request->input('q', ''));
        $searchMode = $this->isExactSearch($request) ? 'exact' : '';
        $status = $this->normalizeStatusFilter($request->input('status'));
        $flowStatus = $this->normalizeFlowStatusFilter($request->input('flow_status'));

        $baseQuery = Resi::query();
        $this->applyUploadDateRange($baseQuery, $filterDateFrom, $filterDateTo);
        $this->applySearch($baseQuery, $search, $this->isExactSearch($request));
        $this->applyStatusFilter($baseQuery, $status);
        $this->applyFlowStatusFilter($baseQuery, $flowStatus);

        $summaryOrders = (clone $baseQuery)->count();
        $summarySkus = ResiDetail::whereIn('resi_id', (clone $baseQuery)->select('id'))->count();

        return view('admin.inventory.resi-import.index', [
            'importUrl' => route('admin.inventory.resi-import.import'),
            'dataUrl' => route('admin.inventory.resi-import.data'),
            'buyerNotesUrl' => route('admin.inventory.resi-import.buyer-notes'),
            'filterDateFrom' => $filterDateFrom,
            'filterDateTo' => $filterDateTo,
            'filterSearch' => $search,
            'filterSearchMode' => $searchMode,
            'filterStatus' => $status,
            'filterFlowStatus' => $flowStatus,
            'flowStatusOptions' => ResiOperationalStatus::options(),
            'today' => $today,
            'summaryOrders' => $summaryOrders,
            'summarySkus' => $summarySkus,
        ]);
    }

    public function data(Request $request)
    {
        [$filterDateFrom, $filterDateTo] = $this->resolveUploadDateRange($request);
        $search = trim((string) $request->input('q', ''));
        $status = $this->normalizeStatusFilter($request->input('status'));
        $flowStatus = $this->normalizeFlowStatusFilter($request->input('flow_status'));

        $filterQuery = Resi::query();
        $this->applyUploadDateRange($filterQuery, $filterDateFrom, $filterDateTo);
        $this->applySearch($filterQuery, $search, $this->isExactSearch($request));
        $this->applyStatusFilter($filterQuery, $status);
        $this->applyFlowStatusFilter($filterQuery, $flowStatus);

        $totalQuery = Resi::query();
        $this->applyUploadDateRange($totalQuery, $filterDateFrom, $filterDateTo);
        $recordsTotal = $totalQuery->count();
        $summaryOrders = (clone $filterQuery)->count();
        $summarySkus = ResiDetail::whereIn('resi_id', (clone $filterQuery)->select('id'))->count();

        $query = Resi::query()
            ->select(['id', 'id_pesanan', 'no_resi', 'tanggal_pesanan', 'kurir_id', 'store_id', 'channel_id', 'status', 'cancel_reason'])
            ->selectSub(function ($sub) {
                $sub->from('shipment_scan_outs')
                    ->selectRaw('count(1)')
                    ->whereColumn('shipment_scan_outs.resi_id', 'resis.id');
            }, 'scan_out_count')
            ->selectSub(function ($sub) {
                $sub->from('qc_resi_scans')
                    ->selectRaw('count(1)')
                    ->whereColumn('qc_resi_scans.resi_id', 'resis.id');
            }, 'qc_scan_count')
            ->selectSub(function ($sub) {
                $sub->from('qc_resi_scans')
                    ->selectRaw('count(1)')
                    ->whereColumn('qc_resi_scans.resi_id', 'resis.id')
                    ->where('qc_resi_scans.status', 'passed');
            }, 'qc_passed_count')
            ->with(['details' => function ($q) {
                $q->select(['id', 'resi_id', 'sku', 'qty']);
            }, 'kurir', 'store:id,name', 'channel:id,name'])
            ->orderByDesc('id');

        $this->applyUploadDateRange($query, $filterDateFrom, $filterDateTo);
        $this->applySearch($query, $search, $this->isExactSearch($request));
        $this->applyStatusFilter($query, $status);
        $this->applyFlowStatusFilter($query, $flowStatus);

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length > 0) {
            $query->skip($start)->take($length);
        }

        $data = $query->get()->map(function ($row) {
            $skuItems = $row->details
                ? $row->details->groupBy('sku')->map(function ($items, $sku) {
                    $total = $items->sum('qty');
                    return $sku.' ('.$total.')';
                })->values()->implode(', ')
                : '-';
            $skuList = $skuItems !== '' ? $skuItems : '-';
            $tanggalOrder = $row->tanggal_pesanan?->format('Y-m-d') ?? $row->tanggal_pesanan ?? '-';
            $hasQcScan = (int) ($row->qc_scan_count ?? 0) > 0;
            $hasQcPassed = (int) ($row->qc_passed_count ?? 0) > 0;
            $hasScanOut = (int) ($row->scan_out_count ?? 0) > 0;
            $operationalStatus = ResiOperationalStatus::resolve(
                $row->status,
                $hasQcScan,
                $hasQcPassed,
                $hasScanOut,
                $row->cancel_reason
            );
            return [
                'id' => $row->id,
                'no_resi' => $row->no_resi ?? '-',
                'id_pesanan' => $row->id_pesanan ?? '-',
                'kurir' => $row->kurir?->name ?? '-',
                'nama_toko' => $row->store?->name ?? '-',
                'channel' => $row->channel?->name ?? '-',
                'sku' => $skuList,
                'tanggal_pesanan' => $tanggalOrder,
                'status' => $row->status ?? 'active',
                'has_qc_scan' => $hasQcScan,
                'has_scan_out' => $hasScanOut,
                'operational_status' => $operationalStatus,
                'operational_status_label' => ResiOperationalStatus::label($operationalStatus),
                'operational_status_badge' => ResiOperationalStatus::badgeClass($operationalStatus),
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $summaryOrders,
            'data' => $data,
            'summary' => [
                'orders' => $summaryOrders,
                'skus' => $summarySkus,
            ],
            'date_from' => $filterDateFrom,
            'date_to' => $filterDateTo,
            'date_label' => $this->uploadDateRangeLabel($filterDateFrom, $filterDateTo),
        ]);
    }

    public function summary(Request $request)
    {
        [$filterDateFrom, $filterDateTo] = $this->resolveUploadDateRange($request);
        $status = $this->normalizeStatusFilter($request->input('status'));
        $flowStatus = $this->normalizeFlowStatusFilter($request->input('flow_status'));

        $baseQuery = DB::table('resi_details as rd')
            ->join('resis as r', 'r.id', '=', 'rd.resi_id');
        $this->applyUploadDateRange($baseQuery, $filterDateFrom, $filterDateTo, 'r.tanggal_upload');

        if ($status !== '') {
            $baseQuery->where('r.status', $status);
        }
        if ($flowStatus !== '') {
            $this->applyFlowStatusFilter($baseQuery, $flowStatus, 'r');
        }

        $rows = (clone $baseQuery)
            ->select('rd.sku', DB::raw('SUM(rd.qty) as qty'))
            ->groupBy('rd.sku')
            ->orderByDesc('qty')
            ->get();

        $totalSku = $rows->count();
        $totalQty = (int) $rows->sum('qty');

        $data = $rows->map(function ($row) {
            return [
                'sku' => $row->sku ?? '-',
                'qty' => (int) ($row->qty ?? 0),
            ];
        });

        return response()->json([
            'date' => $this->uploadDateRangeLabel($filterDateFrom, $filterDateTo),
            'date_from' => $filterDateFrom,
            'date_to' => $filterDateTo,
            'status' => $status,
            'summary' => [
                'total_sku' => $totalSku,
                'total_qty' => $totalQty,
            ],
            'data' => $data,
        ]);
    }

    public function buyerNotes(Request $request)
    {
        [$filterDateFrom, $filterDateTo] = $this->resolveUploadDateRange($request);
        $search = trim((string) $request->input('q', ''));
        $status = $this->normalizeStatusFilter($request->input('status'));
        $flowStatus = $this->normalizeFlowStatusFilter($request->input('flow_status'));

        $query = Resi::query()
            ->select(['id', 'id_pesanan', 'no_resi', 'tanggal_pesanan', 'kurir_id', 'catatan_pembeli', 'status'])
            ->with('kurir:id,name')
            ->whereNotNull('catatan_pembeli')
            ->where('catatan_pembeli', '<>', '')
            ->orderByDesc('id');

        $this->applyUploadDateRange($query, $filterDateFrom, $filterDateTo);
        $this->applySearch($query, $search, $this->isExactSearch($request));
        $this->applyStatusFilter($query, $status);
        $this->applyFlowStatusFilter($query, $flowStatus);

        $rows = $query->get()->map(function ($row) {
            return [
                'id' => $row->id,
                'no_resi' => $row->no_resi ?? '-',
                'id_pesanan' => $row->id_pesanan ?? '-',
                'kurir' => $row->kurir?->name ?? '-',
                'tanggal_pesanan' => $row->tanggal_pesanan?->format('Y-m-d') ?? $row->tanggal_pesanan ?? '-',
                'status' => $row->status ?? 'active',
                'catatan_pembeli' => $row->catatan_pembeli,
            ];
        });

        return response()->json([
            'date' => $this->uploadDateRangeLabel($filterDateFrom, $filterDateTo),
            'date_from' => $filterDateFrom,
            'date_to' => $filterDateTo,
            'total' => $rows->count(),
            'data' => $rows,
        ]);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120'],
        ]);

        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }

        // Only one resi import at a time. Two imports of the same file used to wait on
        // each other's row locks until MySQL gave up (error 1205 Lock wait timeout).
        $lock = Cache::lock(self::IMPORT_LOCK_KEY, 600);
        if (!$lock->get()) {
            throw ValidationException::withMessages([
                'file' => 'Masih ada import resi lain yang sedang berjalan. Tunggu sampai selesai, lalu coba lagi.',
            ]);
        }

        $savedResi = 0;
        $savedDetails = 0;
        $restoreLockTimeout = $this->shortenLockWaitTimeout();

        try {
            // 1) Read, validate and prepare everything first, outside any transaction.
            //    Errors in the file stop the import before anything is saved.
            $plan = $this->buildImportPlan($request->file('file'));
            $today = now()->toDateString();

            // 2) Save in small chunks, each in its own short transaction, so row locks
            //    are held for milliseconds instead of the whole import.
            foreach (array_chunk($plan, self::IMPORT_CHUNK_SIZE) as $chunk) {
                [$chunkResi, $chunkDetails] = DB::transaction(
                    fn () => $this->writeImportChunk($chunk, $today),
                    self::IMPORT_CHUNK_ATTEMPTS
                );
                $savedResi += $chunkResi;
                $savedDetails += $chunkDetails;
            }

            return response()->json([
                'message' => 'Import resi berhasil',
                'resis' => $savedResi,
                'details' => $savedDetails,
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            $message = $this->isLockTimeout($e)
                ? 'Database sedang sibuk (data resi sedang dipakai proses lain seperti scan QC). Silakan coba import lagi beberapa saat.'
                : 'Server gagal memproses import: '.$e->getMessage();
            if ($savedResi > 0) {
                $message .= " Sebagian data ({$savedResi} resi) sudah tersimpan. Import ulang file yang sama aman, data akan diperbarui.";
            }

            return response()->json([
                'message' => 'Gagal import resi',
                'error' => $message,
                'resis' => $savedResi,
                'details' => $savedDetails,
            ], 500);
        } finally {
            $restoreLockTimeout();
            $lock->release();
        }
    }

    /**
     * Parse the Excel file and resolve everything needed to save it, using a fixed
     * number of bulk queries instead of several queries per resi.
     *
     * @return array<int,array<string,mixed>>
     */
    private function buildImportPlan($file): array
    {
        $import = new ResiImport();
        Excel::import($import, $file);
        $groups = array_values($import->groups ?? []);
        if (empty($groups)) {
            throw ValidationException::withMessages([
                'file' => 'Tidak ada data valid untuk diimport',
            ]);
        }
        $this->validateUniqueNoResi($groups);

        $userId = auth()->id();
        $now = now();

        $existingByKey = $this->loadExistingResiByIdPesanan(array_column($groups, 'id_pesanan'));
        $existingIds = array_values(array_unique(array_map(fn (Resi $r) => (int) $r->id, $existingByKey)));
        $operationalIds = $this->operationalResiIds($existingIds);
        $oldDetailsByResi = $this->detailsByResiId($existingIds);

        $plan = [];
        $skusToExpand = [];
        foreach ($groups as $group) {
            $existing = $existingByKey[$this->idPesananKey($group['id_pesanan'])] ?? null;
            if ($existing && isset($operationalIds[(int) $existing->id])) {
                throw ValidationException::withMessages([
                    'file' => 'Resi dengan ID Pesanan '.$group['id_pesanan'].' sudah masuk proses operasional, import ulang diblokir.',
                ]);
            }

            $tanggalPesanan = $this->parseTanggalPesanan($group['tanggal_pesanan'] ?? null);
            if ($tanggalPesanan === null) {
                throw ValidationException::withMessages([
                    'file' => 'Format tanggal_pembuatan tidak valid untuk ID Pesanan: '.$group['id_pesanan'],
                ]);
            }

            $payload = [
                'tanggal_pesanan' => $tanggalPesanan,
                'tanggal_upload' => $now->toDateString(),
                'uploader_id' => $userId,
                'catatan_pembeli' => $group['catatan_pembeli'] ?? null,
            ];
            $importedStatus = $group['status'] ?? null;
            if ($importedStatus === 'canceled') {
                $payload['status'] = 'canceled';
                $payload['canceled_at'] = $existing?->canceled_at ?? $now;
                $payload['canceled_by'] = $existing?->canceled_by ?? $userId;
                $payload['cancel_reason'] = $group['status_raw']
                    ? 'Status import: '.$group['status_raw']
                    : 'Status import: dibatalkan';
                $payload['uncanceled_at'] = null;
                $payload['uncanceled_by'] = null;
            } elseif ($importedStatus === 'active') {
                $payload['status'] = 'active';
                if (($existing?->status ?? 'active') === 'canceled') {
                    $payload['uncanceled_at'] = $now;
                    $payload['uncanceled_by'] = $userId;
                }
            }
            $noResi = isset($group['no_resi']) ? trim((string) $group['no_resi']) : '';
            if ($noResi !== '') {
                $payload['no_resi'] = $noResi;
            }

            $oldDate = $existing?->tanggal_upload?->format('Y-m-d');
            $oldWasCanceled = $existing && ($existing->status ?? 'active') === 'canceled';
            $oldItems = $existing ? ($oldDetailsByResi[(int) $existing->id] ?? []) : [];
            $removeOld = $existing && $oldDate && !$oldWasCanceled;
            $newStatus = $payload['status'] ?? ($existing?->status ?? 'active');
            $addNew = $newStatus !== 'canceled';

            if ($removeOld) {
                array_push($skusToExpand, ...array_column($oldItems, 'sku'));
            }
            if ($addNew) {
                array_push($skusToExpand, ...array_column($group['items'], 'sku'));
            }

            $plan[] = [
                'group' => $group,
                'existing_id' => $existing?->id,
                'payload' => $payload,
                'old_date' => $removeOld ? $oldDate : null,
                'old_items' => $removeOld ? $oldItems : [],
                'add_new' => $addNew,
            ];
        }

        // Unknown SKUs / broken bundles fail here, before anything is written.
        $unitRequirements = BundleService::unitRequirementsBySku($skusToExpand);

        // Master data (kurir, toko, channel) resolved from in-memory maps.
        $defaultKurirId = $this->resolveDefaultKurirId();
        $kurirIdsByName = $this->masterNameMap(Kurir::query()->get(['id', 'name']));
        $storeIdsByName = $this->masterNameMap(Store::query()->get(['id', 'name']));
        $channelIdsByName = $this->masterNameMap(Channel::query()->get(['id', 'name']));

        foreach ($plan as $i => $entry) {
            $group = $entry['group'];
            $kurirId = $this->resolveKurirIdFromMap($group['kurir'] ?? null, $defaultKurirId, $kurirIdsByName);
            if ($kurirId) {
                $plan[$i]['payload']['kurir_id'] = $kurirId;
            }
            $storeId = $this->resolveStoreId($group['nama_toko'] ?? null, $storeIdsByName);
            if ($storeId !== null) {
                $plan[$i]['payload']['store_id'] = $storeId;
            }
            $channelId = $this->resolveChannelId($group['channel'] ?? null, $channelIdsByName);
            if ($channelId !== null) {
                $plan[$i]['payload']['channel_id'] = $channelId;
            }
            $plan[$i]['old_requirements'] = $this->expandWithMap($entry['old_items'], $unitRequirements);
            $plan[$i]['new_requirements'] = $entry['add_new']
                ? $this->expandWithMap($group['items'], $unitRequirements)
                : [];
        }

        return $plan;
    }

    /**
     * Save one chunk of the import plan. Runs inside a short DB transaction.
     *
     * @param array<int,array<string,mixed>> $chunk
     * @return array{0:int,1:int}
     */
    private function writeImportChunk(array $chunk, string $today): array
    {
        $now = now();
        $existingIds = array_values(array_filter(array_column($chunk, 'existing_id')));
        // Fresh copies inside the transaction, so a retried chunk never works on stale models.
        $existingById = $existingIds
            ? Resi::query()->whereIn('id', $existingIds)->get()->keyBy('id')
            : collect();

        if (!empty($existingIds)) {
            ResiDetail::query()->whereIn('resi_id', $existingIds)->delete();
        }

        $savedResi = 0;
        $detailRows = [];
        $pickingChanges = [];

        foreach ($chunk as $entry) {
            $group = $entry['group'];
            $resi = $entry['existing_id'] ? $existingById->get($entry['existing_id']) : null;
            if ($resi) {
                $resi->fill($entry['payload'])->save();
            } else {
                $resi = Resi::create(['id_pesanan' => $group['id_pesanan']] + $entry['payload']);
            }
            $savedResi++;

            foreach ($group['items'] as $row) {
                $detailRows[] = [
                    'resi_id' => $resi->id,
                    'sku' => $row['sku'],
                    'qty' => (int) $row['qty'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if ($entry['old_date']) {
                foreach ($entry['old_requirements'] as $sku => $qty) {
                    $pickingChanges[$entry['old_date']][$sku]['minus'] = ($pickingChanges[$entry['old_date']][$sku]['minus'] ?? 0) + $qty;
                }
            }
            foreach ($entry['new_requirements'] as $sku => $qty) {
                $pickingChanges[$today][$sku]['plus'] = ($pickingChanges[$today][$sku]['plus'] ?? 0) + $qty;
            }
        }

        foreach (array_chunk($detailRows, 500) as $rows) {
            ResiDetail::query()->insert($rows);
        }

        ksort($pickingChanges);
        foreach ($pickingChanges as $date => $changes) {
            $this->applyPickingChanges((string) $date, $changes);
        }

        return [$savedResi, count($detailRows)];
    }

    /**
     * @param array<int,string> $idPesananList
     * @return array<string,Resi> keyed by normalized id_pesanan
     */
    private function loadExistingResiByIdPesanan(array $idPesananList): array
    {
        $map = [];
        $ids = array_values(array_unique(array_map(fn ($v) => trim((string) $v), $idPesananList)));
        foreach (array_chunk($ids, 1000) as $chunk) {
            Resi::query()
                ->whereIn('id_pesanan', $chunk)
                ->orderBy('id')
                ->get()
                ->each(function (Resi $resi) use (&$map) {
                    $map[$this->idPesananKey($resi->id_pesanan)] ??= $resi;
                });
        }

        return $map;
    }

    private function idPesananKey($idPesanan): string
    {
        return mb_strtolower(trim((string) $idPesanan));
    }

    /**
     * Resi ids that already have QC or scan out.
     *
     * @param array<int,int> $resiIds
     * @return array<int,true>
     */
    private function operationalResiIds(array $resiIds): array
    {
        $ids = [];
        foreach (array_chunk($resiIds, 1000) as $chunk) {
            foreach (QcResiScan::query()->whereIn('resi_id', $chunk)->pluck('resi_id') as $id) {
                $ids[(int) $id] = true;
            }
            foreach (ShipmentScanOut::query()->whereIn('resi_id', $chunk)->pluck('resi_id') as $id) {
                $ids[(int) $id] = true;
            }
        }

        return $ids;
    }

    /**
     * @param array<int,int> $resiIds
     * @return array<int,array<int,array{sku:string,qty:int}>>
     */
    private function detailsByResiId(array $resiIds): array
    {
        $map = [];
        foreach (array_chunk($resiIds, 1000) as $chunk) {
            ResiDetail::query()
                ->whereIn('resi_id', $chunk)
                ->orderBy('id')
                ->get(['resi_id', 'sku', 'qty'])
                ->each(function ($row) use (&$map) {
                    $map[(int) $row->resi_id][] = ['sku' => (string) $row->sku, 'qty' => (int) $row->qty];
                });
        }

        return $map;
    }

    /**
     * @param iterable<int,array{sku:string,qty:int}> $items
     * @param array<string,array<int,array{sku:string,qty:int}>> $unitRequirements
     * @return array<string,int> physical sku => qty
     */
    private function expandWithMap(iterable $items, array $unitRequirements): array
    {
        $result = [];
        foreach ($items as $row) {
            $sku = trim((string) ($row['sku'] ?? ''));
            $qty = (int) ($row['qty'] ?? 0);
            if ($sku === '' || $qty <= 0) {
                continue;
            }
            foreach ($unitRequirements[mb_strtolower($sku)] ?? [] as $req) {
                $result[$req['sku']] = ($result[$req['sku']] ?? 0) + $req['qty'] * $qty;
            }
        }

        return $result;
    }

    /**
     * MySQL waits 50 seconds for a row lock by default. For the import we would rather
     * fail fast and let DB::transaction() retry the small chunk.
     *
     * @return callable restores the previous value
     */
    private function shortenLockWaitTimeout(): callable
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return static fn () => null;
        }

        try {
            $previous = (int) DB::selectOne('SELECT @@SESSION.innodb_lock_wait_timeout AS t')->t;
            DB::statement('SET SESSION innodb_lock_wait_timeout = '.self::IMPORT_LOCK_WAIT_SECONDS);
        } catch (\Throwable) {
            return static fn () => null;
        }

        return static function () use ($previous) {
            try {
                DB::statement('SET SESSION innodb_lock_wait_timeout = '.max(1, $previous));
            } catch (\Throwable) {
                // ignore
            }
        };
    }

    private function isLockTimeout(\Throwable $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'Lock wait timeout') || str_contains($message, 'Deadlock found');
    }

    /**
     * @param array<int|string,array{id_pesanan:string,no_resi:?string}> $groups
     */
    private function validateUniqueNoResi(array $groups): void
    {
        $seen = [];
        $lookup = [];

        foreach ($groups as $group) {
            $idPesanan = trim((string) ($group['id_pesanan'] ?? ''));
            $noResi = trim((string) ($group['no_resi'] ?? ''));
            if ($idPesanan === '' || $noResi === '') {
                continue;
            }

            $normalizedNoResi = mb_strtoupper($noResi);
            if (isset($seen[$normalizedNoResi]) && $seen[$normalizedNoResi]['id_pesanan'] !== $idPesanan) {
                throw ValidationException::withMessages([
                    'file' => 'No resi '.$noResi.' terduplikasi di file import untuk ID Pesanan '
                        .$seen[$normalizedNoResi]['id_pesanan'].' dan '.$idPesanan.'.',
                ]);
            }

            $seen[$normalizedNoResi] = [
                'id_pesanan' => $idPesanan,
                'no_resi' => $noResi,
            ];
            $lookup[] = $normalizedNoResi;
        }

        $lookup = array_values(array_unique($lookup));
        if (empty($lookup)) {
            return;
        }

        $existingRows = Resi::query()
            ->whereNotNull('no_resi')
            ->whereIn(DB::raw('UPPER(no_resi)'), $lookup)
            ->get(['id_pesanan', 'no_resi']);

        foreach ($existingRows as $existing) {
            $normalizedNoResi = mb_strtoupper(trim((string) $existing->no_resi));
            $incoming = $seen[$normalizedNoResi] ?? null;
            if (!$incoming || $incoming['id_pesanan'] === trim((string) $existing->id_pesanan)) {
                continue;
            }

            throw ValidationException::withMessages([
                'file' => 'No resi '.$incoming['no_resi'].' sudah dipakai oleh ID Pesanan '
                    .$existing->id_pesanan.', import untuk ID Pesanan '.$incoming['id_pesanan'].' diblokir.',
            ]);
        }
    }

    public function cancel(Request $request)
    {
        $validated = $this->validateResiActionRequest($request, true);
        $resi = $this->findResiForAction($validated);
        if (!$resi) {
            return response()->json([
                'message' => 'Resi tidak ditemukan.',
            ], 404);
        }

        if (($resi->status ?? 'active') === 'canceled') {
            return response()->json([
                'message' => 'Resi sudah dibatalkan sebelumnya.',
            ]);
        }

        $hasScanOut = ShipmentScanOut::where('resi_id', $resi->id)->exists();
        $hasQc = QcResiScan::where('resi_id', $resi->id)->exists();
        if ($hasScanOut) {
            return response()->json([
                'message' => 'Resi sudah scan out, tidak bisa dibatalkan.',
            ], 422);
        }
        if ($hasQc) {
            return response()->json([
                'message' => 'Resi sudah QC, tidak bisa dibatalkan.',
            ], 422);
        }
        DB::beginTransaction();
        try {
            $resi->status = 'canceled';
            $resi->canceled_at = now();
            $resi->canceled_by = auth()->id();
            $resi->cancel_reason = $validated['reason'] ?? null;
            $resi->uncanceled_at = null;
            $resi->uncanceled_by = null;
            $resi->save();

            $details = ResiDetail::where('resi_id', $resi->id)->get(['sku', 'qty']);
            $listDate = $resi->tanggal_upload?->format('Y-m-d') ?? now()->toDateString();
            if ($details->isNotEmpty()) {
                $this->adjustPickingList($listDate, $details, -1);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal membatalkan resi.',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Resi berhasil dibatalkan.',
        ]);
    }

    public function cancelAfterQc(Request $request)
    {
        return $this->cancelProcessedResi($request, false);
    }

    public function cancelAfterShip(Request $request)
    {
        return $this->cancelProcessedResi($request, true);
    }

    public function uncancel(Request $request)
    {
        $validated = $this->validateResiActionRequest($request, false);
        $resi = $this->findResiForAction($validated);
        if (!$resi) {
            return response()->json([
                'message' => 'Resi tidak ditemukan.',
            ], 404);
        }

        if (($resi->status ?? 'active') !== 'canceled') {
            return response()->json([
                'message' => 'Resi tidak dalam status cancel.',
            ], 422);
        }

        $hasScanOut = ShipmentScanOut::where('resi_id', $resi->id)->exists();
        $hasQc = QcResiScan::where('resi_id', $resi->id)->exists();
        if ($hasScanOut) {
            return response()->json([
                'message' => 'Resi sudah memiliki proses scan out, batal cancel tidak diizinkan.',
            ], 422);
        }
        if ($hasQc) {
            return response()->json([
                'message' => 'Resi sudah memiliki proses QC, batal cancel tidak diizinkan. Gunakan import resi baru bila order aktif kembali.',
            ], 422);
        }

        $details = ResiDetail::where('resi_id', $resi->id)->get(['sku', 'qty']);
        if ($details->isEmpty()) {
            return response()->json([
                'message' => 'Detail resi tidak ditemukan, batal cancel tidak dapat diproses.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $resi->status = 'active';
            $resi->uncanceled_at = now();
            $resi->uncanceled_by = auth()->id();
            $resi->save();

            $listDate = $resi->tanggal_upload?->format('Y-m-d') ?? now()->toDateString();
            $this->adjustPickingList($listDate, $details, 1);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal membatalkan status cancel resi.',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Status cancel resi berhasil dibatalkan.',
        ]);
    }

    public function destroy(int $id)
    {
        DB::beginTransaction();
        try {
            $resi = Resi::whereKey($id)->lockForUpdate()->first();
            if (!$resi) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Resi tidak ditemukan.',
                ], 404);
            }

            $scanOut = ShipmentScanOut::where('resi_id', $resi->id)
                ->lockForUpdate()
                ->first(['id']);
            $qc = QcResiScan::where('resi_id', $resi->id)
                ->lockForUpdate()
                ->first(['id']);

            if ($qc || $scanOut) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Resi sudah masuk proses QC atau scan out, tidak bisa dihapus.',
                ], 422);
            }

            $details = ResiDetail::where('resi_id', $resi->id)->get(['sku', 'qty']);
            $listDate = $resi->tanggal_upload?->format('Y-m-d') ?? now()->toDateString();
            if (($resi->status ?? 'active') !== 'canceled' && $details->isNotEmpty()) {
                $this->adjustPickingList($listDate, $details, -1);
                SameDay::apply(PickingList::query(), 'list_date', $listDate)
                    ->whereIn('sku', $details->pluck('sku')->filter()->unique()->values()->all())
                    ->where('qty', '<=', 0)
                    ->where('remaining_qty', '<=', 0)
                    ->delete();
            }

            $resi->delete();

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menghapus resi.',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Resi berhasil dihapus.',
        ]);
    }

    private function cancelProcessedResi(Request $request, bool $requiresScanOut)
    {
        $validated = $this->validateResiActionRequest($request, true);
        $resi = $this->findResiForAction($validated);
        if (!$resi) {
            return response()->json([
                'message' => 'Resi tidak ditemukan.',
            ], 404);
        }

        if (($resi->status ?? 'active') === 'canceled') {
            return response()->json([
                'message' => 'Resi sudah dibatalkan sebelumnya.',
            ]);
        }

        DB::beginTransaction();
        try {
            $resi = Resi::whereKey($resi->id)->lockForUpdate()->firstOrFail();
            $qc = QcResiScan::where('resi_id', $resi->id)
                ->lockForUpdate()
                ->first();
            $scanOut = ShipmentScanOut::where('resi_id', $resi->id)
                ->lockForUpdate()
                ->first();

            if (!$qc || ($qc->status ?? '') !== QcTransitStatus::PASSED) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Resi belum lolos QC, gunakan cancel resi biasa.',
                ], 422);
            }

            if ($requiresScanOut && !$scanOut) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Resi belum scan out. Gunakan Cancel Ready to Ship.',
                ], 422);
            }

            if (!$requiresScanOut && $scanOut) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Resi sudah scan out. Gunakan Cancel After Ship.',
                ], 422);
            }

            $details = ResiDetail::where('resi_id', $resi->id)->get(['sku', 'qty']);
            if ($details->isEmpty()) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Detail resi tidak ditemukan, cancel koreksi tidak dapat diproses.',
                ], 422);
            }

            $uploadDate = $resi->tanggal_upload?->format('Y-m-d') ?? now()->toDateString();
            $syncSkus = $details->pluck('sku');
            $substitutionSkus = $qc->substitutions()
                ->get(['original_sku', 'replacement_sku'])
                ->flatMap(fn ($row) => [$row->original_sku, $row->replacement_sku]);

            if ($scanOut) {
                $scanOut->delete();
            }

            StockService::rollbackBySource('qc_shipment', $qc->id);
            StockMutation::where('source_type', 'qc_shipment')
                ->where('source_id', $qc->id)
                ->delete();

            $qc->status = QcTransitStatus::CANCELED;
            $qc->save();

            $resi->status = 'canceled';
            $resi->canceled_at = now();
            $resi->canceled_by = auth()->id();
            $resi->cancel_reason = $this->buildProcessedCancelReason(
                $requiresScanOut ? 'Cancel After Ship' : 'Cancel Ready to Ship',
                $validated['reason'] ?? null
            );
            $resi->uncanceled_at = null;
            $resi->uncanceled_by = null;
            $resi->save();

            $this->adjustPickingList($uploadDate, $details, -1);
            PickingListBalanceService::syncForDateSkus(
                $uploadDate,
                $syncSkus->merge($substitutionSkus)->filter()->all()
            );

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => $requiresScanOut
                    ? 'Gagal memproses Cancel After Ship.'
                    : 'Gagal memproses Cancel Ready to Ship.',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => $requiresScanOut
                ? 'Cancel After Ship berhasil diproses.'
                : 'Cancel Ready to Ship berhasil diproses.',
        ]);
    }

    private function buildProcessedCancelReason(string $label, ?string $reason): string
    {
        $reason = trim((string) $reason);

        return substr($reason === '' ? $label : $label.': '.$reason, 0, 255);
    }

    private function parseTanggalPesanan($raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (is_numeric($raw)) {
            try {
                $dt = ExcelDate::excelToDateTimeObject($raw);
                return $dt->format('Y-m-d');
            } catch (\Throwable $e) {
                return null;
            }
        }
        try {
            return Carbon::parse($raw)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function adjustPickingList(string $date, $items, int $direction): void
    {
        $changes = [];
        foreach (BundleService::expandSkuRows($items) as $row) {
            $sku = trim((string) ($row['sku'] ?? ''));
            $qty = (int) ($row['qty'] ?? 0);
            if ($sku === '' || $qty <= 0) {
                continue;
            }
            $key = $direction < 0 ? 'minus' : 'plus';
            $changes[$sku][$key] = ($changes[$sku][$key] ?? 0) + $qty;
        }

        $this->applyPickingChanges($date, $changes);
    }

    /**
     * Apply picking list changes for many SKUs of one date with a handful of queries.
     * Rows are locked through the (list_date, sku) unique index in SKU order, so only
     * the touched rows are locked and concurrent writers always lock in the same order.
     *
     * @param array<string,array{minus?:int,plus?:int}> $changes physical sku => qty to remove / add
     */
    private function applyPickingChanges(string $date, array $changes): void
    {
        if (empty($changes)) {
            return;
        }
        ksort($changes, SORT_STRING | SORT_FLAG_CASE);
        $skus = array_map('strval', array_keys($changes));

        $listRows = SameDay::apply(PickingList::query(), 'list_date', $date)
            ->whereIn('sku', $skus)
            ->orderBy('sku')
            ->lockForUpdate()
            ->get()
            ->keyBy(fn ($row) => mb_strtolower((string) $row->sku));
        $exceptionRows = SameDay::apply(PickingListException::query(), 'list_date', $date)
            ->whereIn('sku', $skus)
            ->orderBy('sku')
            ->lockForUpdate()
            ->get()
            ->keyBy(fn ($row) => mb_strtolower((string) $row->sku));
        $pickedBySku = $this->pickedQtyMap($date, $skus);
        $substitutedBySku = $this->substitutedOutQtyMap($date, $skus);

        foreach ($changes as $sku => $change) {
            $sku = (string) $sku;
            $key = mb_strtolower($sku);
            $listRow = $listRows->get($key);
            $currentQty = (int) ($listRow?->qty ?? 0);
            // Same order as before: remove the old upload first, then add the new one.
            $newQty = max(0, max(0, $currentQty - (int) ($change['minus'] ?? 0)) + (int) ($change['plus'] ?? 0));

            $pickedQty = (int) ($pickedBySku[$key] ?? 0);
            if ($newQty <= 0) {
                $remaining = 0;
                $exceptionQty = $pickedQty;
            } else {
                $effectivePicked = $pickedQty + (int) ($substitutedBySku[$key] ?? 0);
                $remaining = max(0, $newQty - $effectivePicked);
                $exceptionQty = max(0, $pickedQty - $newQty);
            }

            if ($listRow) {
                $listRow->qty = $newQty;
                $listRow->remaining_qty = $remaining;
                if ($listRow->qty <= 0 && $listRow->remaining_qty <= 0) {
                    $listRow->delete();
                } else {
                    $listRow->save();
                }
            } elseif ($newQty > 0) {
                PickingList::create([
                    'list_date' => $date,
                    'sku' => $sku,
                    'qty' => $newQty,
                    'remaining_qty' => $remaining,
                ]);
            }

            $exception = $exceptionRows->get($key);
            if ($exceptionQty > 0) {
                if ($exception) {
                    $exception->qty = $exceptionQty;
                    $exception->save();
                } else {
                    PickingListException::create([
                        'list_date' => $date,
                        'sku' => $sku,
                        'qty' => $exceptionQty,
                    ]);
                }
            } elseif ($exception) {
                $exception->delete();
            }
        }
    }

    /**
     * @param array<int,string> $skus
     * @return array<string,int> lower-case sku => qty picked (QC passed) for resi uploaded on $date
     */
    private function pickedQtyMap(string $date, array $skus): array
    {
        $knownSkus = Item::query()
            ->whereIn('sku', $skus)
            ->pluck('sku')
            ->mapWithKeys(fn ($sku) => [mb_strtolower((string) $sku) => true]);

        $query = DB::table('qc_resi_scan_items as qci')
            ->join('qc_resi_scans as qc', 'qc.id', '=', 'qci.qc_resi_scan_id')
            ->join('resis as r', 'r.id', '=', 'qc.resi_id')
            ->where('qc.status', 'passed');

        $map = [];
        SameDay::apply($query, 'r.tanggal_upload', $date)
            ->whereIn('qci.sku', $skus)
            ->groupBy('qci.sku')
            ->select('qci.sku', DB::raw('SUM(qci.expected_qty) as total'))
            ->get()
            ->each(function ($row) use (&$map, $knownSkus) {
                $key = mb_strtolower((string) $row->sku);
                if ($knownSkus->has($key)) {
                    $map[$key] = ($map[$key] ?? 0) + (int) $row->total;
                }
            });

        return $map;
    }

    /**
     * @param array<int,string> $skus
     * @return array<string,int> lower-case sku => qty substituted out for resi uploaded on $date
     */
    private function substitutedOutQtyMap(string $date, array $skus): array
    {
        $query = DB::table('qc_resi_scan_substitutions as sub')
            ->join('qc_resi_scans as qc', 'qc.id', '=', 'sub.qc_resi_scan_id')
            ->join('resis as r', 'r.id', '=', 'qc.resi_id')
            ->where('qc.status', 'passed');

        $map = [];
        SameDay::apply($query, 'r.tanggal_upload', $date)
            ->whereIn('sub.original_sku', $skus)
            ->groupBy('sub.original_sku')
            ->select('sub.original_sku as sku', DB::raw('SUM(sub.qty) as total'))
            ->get()
            ->each(function ($row) use (&$map) {
                $key = mb_strtolower((string) $row->sku);
                $map[$key] = ($map[$key] ?? 0) + (int) $row->total;
            });

        return $map;
    }

    /**
     * @return array{0:string,1:string}
     */
    private function resolveUploadDateRange(Request $request): array
    {
        $today = now()->toDateString();
        $legacyDate = trim((string) $request->input('date', ''));
        $dateFrom = trim((string) $request->input('date_from', ''));
        $dateTo = trim((string) $request->input('date_to', ''));

        if ($dateFrom === '' && $dateTo === '') {
            $dateFrom = $legacyDate !== '' ? $legacyDate : $today;
            $dateTo = $dateFrom;
        } elseif ($dateFrom === '') {
            $dateFrom = $dateTo;
        } elseif ($dateTo === '') {
            $dateTo = $dateFrom;
        }

        $dateFrom = $this->normalizeUploadDate($dateFrom);
        $dateTo = $this->normalizeUploadDate($dateTo);

        if ($dateFrom === null && $dateTo === null) {
            $dateFrom = $today;
            $dateTo = $today;
        } elseif ($dateFrom === null) {
            $dateFrom = $dateTo;
        } elseif ($dateTo === null) {
            $dateTo = $dateFrom;
        }

        if ($dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        return [$dateFrom, $dateTo];
    }

    private function normalizeUploadDate(string $date): ?string
    {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $matches)) {
            return null;
        }

        return checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1])
            ? $date
            : null;
    }

    private function applyUploadDateRange($query, string $dateFrom, string $dateTo, string $column = 'tanggal_upload'): void
    {
        // Plain range instead of whereDate() so the tanggal_upload index can be used.
        $query->where($column, '>=', $dateFrom)
            ->where($column, '<', Carbon::parse($dateTo)->addDay()->toDateString());
    }

    private function uploadDateRangeLabel(string $dateFrom, string $dateTo): string
    {
        return $dateFrom === $dateTo ? $dateFrom : $dateFrom.' s.d. '.$dateTo;
    }

    private function applySearch($query, string $search, bool $exact = false): void
    {
        if ($search === '') {
            return;
        }
        $query->where(function ($sub) use ($search, $exact) {
            $this->applyTextSearch($sub, 'no_resi', $search, $exact);
            $this->applyTextSearch($sub, 'id_pesanan', $search, $exact, 'or');
            $sub->orWhereHas('kurir', function ($kurirQ) use ($search, $exact) {
                $this->applyTextSearch($kurirQ, 'name', $search, $exact);
            })->orWhereHas('store', function ($storeQ) use ($search, $exact) {
                $this->applyTextSearch($storeQ, 'name', $search, $exact);
            })->orWhereHas('channel', function ($channelQ) use ($search, $exact) {
                $this->applyTextSearch($channelQ, 'name', $search, $exact);
            })->orWhereHas('details', function ($detailQ) use ($search, $exact) {
                $this->applyTextSearch($detailQ, 'sku', $search, $exact);
            });
        });
    }

    private function validateResiActionRequest(Request $request, bool $withReason = false): array
    {
        $rules = [
            'id_pesanan' => ['nullable', 'string', 'max:100', 'required_without:no_resi'],
            'no_resi' => ['nullable', 'string', 'max:100', 'required_without:id_pesanan'],
        ];

        if ($withReason) {
            $rules['reason'] = ['nullable', 'string', 'max:255'];
        }

        return $request->validate($rules);
    }

    private function findResiForAction(array $validated): ?Resi
    {
        $resiQuery = Resi::query();
        if (!empty($validated['id_pesanan'])) {
            $resiQuery->where('id_pesanan', trim((string) $validated['id_pesanan']));
        } else {
            $resiQuery->where('no_resi', trim((string) ($validated['no_resi'] ?? '')));
        }

        return $resiQuery->first();
    }

    private function applyStatusFilter($query, string $status): void
    {
        if ($status === '') {
            return;
        }

        $query->where('status', $status);
    }

    private function normalizeStatusFilter($status): string
    {
        $status = trim((string) $status);

        return in_array($status, ['active', 'canceled'], true) ? $status : '';
    }

    private function applyFlowStatusFilter($query, string $flowStatus, string $tableAlias = 'resis'): void
    {
        ResiOperationalStatus::applyFilter($query, $flowStatus, $tableAlias);
    }

    private function normalizeFlowStatusFilter($status): string
    {
        return ResiOperationalStatus::normalize($status);
    }

    private function resolveDefaultKurirId(): ?int
    {
        $defaultName = 'Tidak ditemukan kurir';
        $existingId = Kurir::where('name', $defaultName)->value('id');
        if ($existingId) {
            return (int) $existingId;
        }

        $kurir = Kurir::create(['name' => $defaultName]);
        return $kurir->id;
    }

    /** @param array<string,int> $knownIds */
    private function resolveKurirIdFromMap($rawName, ?int $defaultId, array &$knownIds): ?int
    {
        $name = trim((string) $rawName);
        if ($name === '') {
            return $defaultId;
        }

        $key = $this->normalizeMasterName($name);
        if (isset($knownIds[$key])) {
            return $knownIds[$key];
        }

        $kurir = Kurir::firstOrCreate(['name' => $name]);
        return $knownIds[$key] = (int) $kurir->id;
    }

    /**
     * @param iterable<int,object{id:int,name:string}> $rows
     * @return array<string,int>
     */
    private function masterNameMap(iterable $rows): array
    {
        $map = [];
        foreach ($rows as $row) {
            $map[$this->normalizeMasterName($row->name)] ??= (int) $row->id;
        }

        return $map;
    }

    /** @param array<string,int> $knownIds */
    private function resolveStoreId($rawName, array &$knownIds): ?int
    {
        $name = $this->cleanMasterName($rawName);
        if ($name === '') {
            return null;
        }

        $key = $this->normalizeMasterName($name);
        if (isset($knownIds[$key])) {
            return $knownIds[$key];
        }

        $store = Store::create(['name' => $name]);
        return $knownIds[$key] = (int) $store->id;
    }

    /** @param array<string,int> $knownIds */
    private function resolveChannelId($rawName, array &$knownIds): ?int
    {
        $name = $this->cleanMasterName($rawName);
        if ($name === '') {
            return null;
        }

        $key = $this->normalizeMasterName($name);
        if (isset($knownIds[$key])) {
            return $knownIds[$key];
        }

        $channel = Channel::create(['name' => $name]);
        return $knownIds[$key] = (int) $channel->id;
    }

    private function cleanMasterName($name): string
    {
        $name = trim((string) $name);
        return preg_replace('/\s+/u', ' ', $name) ?: $name;
    }

    private function normalizeMasterName($name): string
    {
        return mb_strtolower($this->cleanMasterName($name));
    }
}
