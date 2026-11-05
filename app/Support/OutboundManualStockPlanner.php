<?php

namespace App\Support;

use App\Models\Item;
use App\Models\ItemStock;
use App\Models\OutboundItem;
use App\Models\OutboundTransaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Validasi stok outbound manual dan rekomendasi transfer Gudang Besar -> Gudang Display.
 *
 * Order manual dari Gudang Display boleh memakai stok Display + Gudang Besar. Barang tetap
 * diambil dari Display, jadi kekurangan Display harus ditransfer dulu (per koli utuh).
 * Order manual yang belum di-approve belum memotong stok, jadi qty-nya dihitung sebagai
 * stok yang sudah dipesan.
 */
class OutboundManualStockPlanner
{
    public const OPEN_STATUSES = [
        OutboundManualQcStatus::PENDING_QC,
        OutboundManualQcStatus::QC_SCANNING,
        OutboundManualQcStatus::PENDING,
    ];

    public const STATE_READY = 'ready';
    public const STATE_WAITING_TRANSFER = 'waiting_transfer';
    public const STATE_NEEDS_TRANSFER = 'needs_transfer';

    /**
     * Pastikan stok cukup untuk order manual baru/diubah. Mengembalikan daftar SKU yang
     * stok Display-nya kurang beserta rekomendasi transfernya (kosong bila Display cukup).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function assertAvailable(iterable $rows, int $warehouseId, ?int $excludeTransactionId = null): array
    {
        [$displayId, $mainId] = self::warehouseIds();
        if ($displayId === null || $mainId === null || ! in_array($warehouseId, [$displayId, $mainId], true)) {
            StockService::assertSellableAvailable($rows, $warehouseId);

            return [];
        }

        $requirements = BundleService::expandItemRows($rows);
        if (empty($requirements)) {
            return [];
        }

        $itemIds = collect($requirements)->pluck('item_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        $snapshot = self::snapshot($itemIds, $excludeTransactionId, true);
        $koliQty = Item::query()->whereIn('id', $itemIds)->pluck('koli_qty', 'id');

        $plan = [];
        foreach ($requirements as $requirement) {
            $itemId = (int) $requirement['item_id'];
            $sku = (string) $requirement['sku'];
            $need = (int) $requirement['qty'];
            $s = $snapshot[$itemId];

            $perKoli = (int) ($koliQty[$itemId] ?? 0);
            $displayFree = $s['display'] + $s['incoming'] - $s['reserved_display'];
            // Order Display lain yang belum tertutup stok Display akan mengambil dari Gudang Besar,
            // dan transfernya per koli utuh.
            // Sisa koli dari transfer itu ikut masuk Display dan bisa dipakai order berikutnya.
            $displayDeficit = max(0, -$displayFree);
            if ($displayDeficit > 0 && $perKoli > 0) {
                $displayDeficit = (int) ceil($displayDeficit / $perKoli) * $perKoli;
            }
            $mainFree = max(0, $s['main'] - $s['reserved_main'] - $displayDeficit);
            $displayFree = max(0, $displayFree + $displayDeficit);

            if ($warehouseId === $mainId) {
                if ($need > $mainFree) {
                    throw ValidationException::withMessages([
                        'qty' => "Stok tidak mencukupi untuk SKU {$sku}. Tersedia {$mainFree}, dibutuhkan {$need}.",
                    ]);
                }

                continue;
            }

            $shortfall = max(0, $need - $displayFree);
            if ($shortfall === 0) {
                continue;
            }

            $available = $displayFree + $mainFree;
            if ($need > $available) {
                throw ValidationException::withMessages([
                    'qty' => "Stok tidak mencukupi untuk SKU {$sku}. Tersedia {$available} (Display {$displayFree} + Gudang Besar {$mainFree}), dibutuhkan {$need}.",
                ]);
            }

            if ($perKoli < 1) {
                throw ValidationException::withMessages([
                    'qty' => "Stok Display SKU {$sku} kurang {$shortfall} pcs dan isi/koli item belum diset, jadi transfer dari Gudang Besar belum bisa dibuat.",
                ]);
            }

            $koli = (int) ceil($shortfall / $perKoli);
            $transferQty = $koli * $perKoli;
            if ($transferQty > $mainFree) {
                throw ValidationException::withMessages([
                    'qty' => "Stok tidak mencukupi untuk SKU {$sku}. Display kurang {$shortfall} pcs, butuh transfer {$koli} koli ({$transferQty} pcs) dari Gudang Besar, tetapi stok Gudang Besar yang tersedia {$mainFree} pcs.",
                ]);
            }

            $plan[] = [
                'item_id' => $itemId,
                'sku' => $sku,
                'required_qty' => $need,
                'display_available_qty' => $displayFree,
                'shortfall_qty' => $shortfall,
                'qty_per_koli' => $perKoli,
                'recommended_koli' => $koli,
                'recommended_transfer_qty' => $transferQty,
            ];
        }

        return $plan;
    }

    /**
     * Status replenishment terkini untuk order manual yang masih terbuka di Gudang Display.
     * Stok Display dibagi berurutan: order yang sudah selesai QC dulu, lalu order paling lama.
     *
     * @param  iterable<OutboundTransaction>  $transactions
     * @return array<int, array<string, mixed>> keyed by transaction id
     */
    public static function statusFor(iterable $transactions): array
    {
        [$displayId, $mainId] = self::warehouseIds();
        $targets = collect($transactions)->filter(fn (OutboundTransaction $tx) => $displayId !== null
            && $mainId !== null
            && $tx->type === 'manual'
            && (int) $tx->warehouse_id === $displayId
            && in_array($tx->status ?? '', self::OPEN_STATUSES, true));

        $result = [];
        foreach ($transactions as $tx) {
            $result[(int) $tx->id] = null;
        }
        if ($targets->isEmpty()) {
            return $result;
        }

        $queue = self::openRequirements([$displayId])
            ->sortBy(fn (array $row) => [$row['status'] === OutboundManualQcStatus::PENDING ? 0 : 1, $row['transaction_id']])
            ->values();
        $targetIds = $targets->pluck('id')->map(fn ($id) => (int) $id)->all();

        $itemIds = $queue->flatMap(fn (array $row) => array_keys($row['items']))->unique()->values()->all();
        $snapshot = self::snapshot($itemIds, null, false);
        $items = Item::query()->whereIn('id', $itemIds)->get(['id', 'sku', 'name', 'koli_qty'])->keyBy('id');

        $prior = [];
        // Transfer yang direkomendasikan untuk order lebih awal (koli utuh) juga menutup order berikutnya.
        $planned = [];
        foreach ($queue as $row) {
            $txId = $row['transaction_id'];
            // Semua order di antrian dihitung (bukan hanya target) supaya urutan dan rencana transfer benar.
            $lines = [];
            $state = self::STATE_READY;
            foreach ($row['items'] as $itemId => $need) {
                $s = $snapshot[$itemId];
                $before = $prior[$itemId] ?? 0;
                $onHand = $s['display'] - $before;
                if ($onHand >= $need) {
                    continue;
                }

                $incoming = (int) $s['incoming'];
                $plannedQty = (int) ($planned[$itemId] ?? 0);
                $item = $items->get($itemId);
                $perKoli = (int) ($item?->koli_qty ?? 0);
                $coveredByEarlierPlan = false;
                if ($s['display'] + $incoming - $before >= $need) {
                    $lineState = self::STATE_WAITING_TRANSFER;
                    $shortfall = 0;
                } else {
                    // Belum ada transfer nyata; bisa jadi sisa koli dari rekomendasi order sebelumnya cukup.
                    $lineState = self::STATE_NEEDS_TRANSFER;
                    $shortfall = max(0, $need - max(0, $s['display'] + $incoming + $plannedQty - $before));
                    $coveredByEarlierPlan = $shortfall === 0;
                }
                $koli = $shortfall > 0 && $perKoli > 0 ? (int) ceil($shortfall / $perKoli) : 0;

                if ($lineState === self::STATE_NEEDS_TRANSFER) {
                    $state = self::STATE_NEEDS_TRANSFER;
                } elseif ($state === self::STATE_READY) {
                    $state = self::STATE_WAITING_TRANSFER;
                }

                $lines[] = [
                    'item_id' => (int) $itemId,
                    'sku' => $item?->sku,
                    'name' => $item?->name,
                    'required_qty' => (int) $need,
                    'display_available_qty' => max(0, $onHand),
                    'incoming_transfer_qty' => (int) $incoming,
                    'shortfall_qty' => (int) $shortfall,
                    'qty_per_koli' => $perKoli,
                    'recommended_koli' => $koli,
                    'recommended_transfer_qty' => $koli * $perKoli,
                    'covered_by_earlier_order' => $coveredByEarlierPlan,
                    'state' => $lineState,
                ];
            }

            if (in_array($txId, $targetIds, true)) {
                $result[$txId] = [
                    'state' => $state,
                    'items' => $lines,
                ];
            }

            foreach ($lines as $line) {
                $planned[$line['item_id']] = ($planned[$line['item_id']] ?? 0) + $line['recommended_transfer_qty'];
            }

            foreach ($row['items'] as $itemId => $need) {
                $prior[$itemId] = ($prior[$itemId] ?? 0) + $need;
            }
        }

        return $result;
    }

    /**
     * Saat QC selesai, barang sudah harus ada di Display: stok Display dikurangi order lain
     * yang sudah selesai QC (menunggu approval) harus cukup untuk order ini.
     */
    public static function assertReadyForQcComplete(OutboundTransaction $transaction): void
    {
        [$displayId, $mainId] = self::warehouseIds();
        if ($displayId === null || $mainId === null || (int) $transaction->warehouse_id !== $displayId) {
            return;
        }

        $requirements = BundleService::expandItemRows($transaction->items);
        if (empty($requirements)) {
            return;
        }

        $itemIds = collect($requirements)->pluck('item_id')->map(fn ($id) => (int) $id)->all();
        $stocks = ItemStock::query()
            ->where('warehouse_id', $displayId)
            ->whereIn('item_id', $itemIds)
            ->pluck('stock', 'item_id');

        $awaitingApproval = [];
        self::openRequirements([$displayId], (int) $transaction->id)
            ->where('status', OutboundManualQcStatus::PENDING)
            ->each(function (array $row) use (&$awaitingApproval) {
                foreach ($row['items'] as $itemId => $qty) {
                    $awaitingApproval[$itemId] = ($awaitingApproval[$itemId] ?? 0) + $qty;
                }
            });

        $short = [];
        foreach ($requirements as $requirement) {
            $itemId = (int) $requirement['item_id'];
            $available = max(0, (int) ($stocks[$itemId] ?? 0) - ($awaitingApproval[$itemId] ?? 0));
            if ($available < (int) $requirement['qty']) {
                $short[] = "{$requirement['sku']} (tersedia {$available}, dibutuhkan {$requirement['qty']})";
            }
        }

        if (! empty($short)) {
            throw ValidationException::withMessages([
                'qty' => 'Stok Gudang Display belum cukup: '.implode(', ', $short).'. Selesaikan transfer dari Gudang Besar terlebih dahulu.',
            ]);
        }
    }

    /**
     * Link ke halaman transfer stok dengan item dan koli yang sudah terisi.
     *
     * @param  array<int, array<string, mixed>>  $lines
     */
    public static function transferUrl(OutboundTransaction $transaction, array $lines): ?string
    {
        [$displayId, $mainId] = self::warehouseIds();
        $items = collect($lines)
            ->filter(fn (array $line) => ($line['recommended_koli'] ?? 0) > 0)
            ->map(fn (array $line) => $line['item_id'].':'.$line['recommended_koli'])
            ->values();

        if ($displayId === null || $mainId === null || $items->isEmpty()) {
            return null;
        }

        return route('admin.inventory.stock-transfers.index', [
            'from' => $mainId,
            'to' => $displayId,
            'items' => $items->implode(','),
            'note' => 'Replenishment outbound manual '.$transaction->code,
        ]);
    }

    /** @return array{0: int|null, 1: int|null} */
    private static function warehouseIds(): array
    {
        $displayId = WarehouseService::warehouseIdByCode(WarehouseService::displayWarehouseCode());
        $mainId = WarehouseService::warehouseIdByCode(WarehouseService::defaultWarehouseCode());
        if ($displayId !== null && $displayId === $mainId) {
            $mainId = null;
        }

        return [$displayId, $mainId];
    }

    /**
     * @param  array<int>  $itemIds
     * @return array<int, array{display: int, main: int, incoming: int, reserved_display: int, reserved_main: int}>
     */
    private static function snapshot(array $itemIds, ?int $excludeTransactionId, bool $lock): array
    {
        [$displayId, $mainId] = self::warehouseIds();
        $snapshot = [];
        foreach ($itemIds as $itemId) {
            $snapshot[(int) $itemId] = ['display' => 0, 'main' => 0, 'incoming' => 0, 'reserved_display' => 0, 'reserved_main' => 0];
        }
        if (empty($snapshot)) {
            return $snapshot;
        }

        $stockQuery = ItemStock::query()
            ->whereIn('warehouse_id', [$displayId, $mainId])
            ->whereIn('item_id', array_keys($snapshot))
            ->orderBy('id');
        if ($lock) {
            // Kunci baris stok supaya dua order bersamaan tidak lolos untuk stok yang sama.
            $stockQuery->lockForUpdate();
        }
        foreach ($stockQuery->get(['id', 'item_id', 'warehouse_id', 'stock']) as $stock) {
            $key = (int) $stock->warehouse_id === $displayId ? 'display' : 'main';
            $snapshot[(int) $stock->item_id][$key] += (int) $stock->stock;
        }

        // Transfer yang sudah dikirim dari gudang asal tetapi belum diterima/QC di Display.
        DB::table('stock_transfer_items as sti')
            ->join('stock_transfers as st', 'st.id', '=', 'sti.stock_transfer_id')
            ->where('st.status', 'qc_pending')
            ->where('st.to_warehouse_id', $displayId)
            ->whereIn('sti.item_id', array_keys($snapshot))
            ->groupBy('sti.item_id')
            ->selectRaw('sti.item_id, SUM(sti.qty) as qty')
            ->get()
            ->each(function ($row) use (&$snapshot) {
                $snapshot[(int) $row->item_id]['incoming'] += (int) $row->qty;
            });

        self::openRequirements([$displayId, $mainId], $excludeTransactionId)
            ->each(function (array $row) use (&$snapshot, $displayId) {
                $key = $row['warehouse_id'] === $displayId ? 'reserved_display' : 'reserved_main';
                foreach ($row['items'] as $itemId => $qty) {
                    if (isset($snapshot[$itemId])) {
                        $snapshot[$itemId][$key] += $qty;
                    }
                }
            });

        return $snapshot;
    }

    /**
     * Kebutuhan item fisik (bundle sudah dipecah) dari order manual yang belum memotong stok.
     *
     * @param  array<int>  $warehouseIds
     * @return Collection<int, array{transaction_id: int, warehouse_id: int, status: string, items: array<int, int>}>
     */
    private static function openRequirements(array $warehouseIds, ?int $excludeTransactionId = null): Collection
    {
        $transactions = OutboundTransaction::query()
            ->where('type', 'manual')
            ->whereIn('status', self::OPEN_STATUSES)
            ->whereIn('warehouse_id', $warehouseIds)
            ->when($excludeTransactionId, fn ($q) => $q->whereKeyNot($excludeTransactionId))
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('stock_mutations')
                    ->where('stock_mutations.source_type', 'outbound')
                    ->whereColumn('stock_mutations.source_id', 'outbound_transactions.id');
            })
            ->get(['id', 'warehouse_id', 'status']);

        if ($transactions->isEmpty()) {
            return collect();
        }

        $rows = OutboundItem::query()
            ->whereIn('outbound_transaction_id', $transactions->pluck('id')->all())
            ->get(['outbound_transaction_id', 'item_id', 'qty'])
            ->groupBy('outbound_transaction_id');

        $items = Item::query()
            ->with('bundleComponents')
            ->whereIn('id', $rows->flatten(1)->pluck('item_id')->unique()->all())
            ->get()
            ->keyBy('id');

        return $transactions->map(function (OutboundTransaction $tx) use ($rows, $items) {
            $needs = [];
            foreach ($rows->get($tx->id, collect()) as $row) {
                $item = $items->get((int) $row->item_id);
                $qty = (int) $row->qty;
                if (! $item || $qty <= 0) {
                    continue;
                }
                if ($item->isBundle()) {
                    foreach ($item->bundleComponents as $component) {
                        $componentId = (int) $component->component_item_id;
                        $needs[$componentId] = ($needs[$componentId] ?? 0) + $qty * (int) $component->required_qty;
                    }
                } else {
                    $needs[(int) $item->id] = ($needs[(int) $item->id] ?? 0) + $qty;
                }
            }

            return [
                'transaction_id' => (int) $tx->id,
                'warehouse_id' => (int) $tx->warehouse_id,
                'status' => (string) $tx->status,
                'items' => $needs,
            ];
        })->values();
    }
}
