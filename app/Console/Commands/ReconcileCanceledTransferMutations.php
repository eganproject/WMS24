<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileCanceledTransferMutations extends Command
{
    protected $signature = 'stock:reconcile-canceled-transfers
        {--execute : Terapkan rekonsiliasi. Tanpa opsi ini command hanya audit.}
        {--batch=20 : Jumlah mutasi yang diproses per transaksi.}
        {--expected= : Jumlah mutasi void yang diharapkan sebagai pengaman tambahan.}';

    protected $description = 'Membuat jurnal pembalik untuk transfer canceled dan menyelaraskan snapshot mutasi tanpa mengubah item_stocks.';

    public function handle(): int
    {
        $batchSize = max(1, min(100, (int) $this->option('batch')));
        $canceledTransferIds = DB::table('stock_transfers')
            ->where('status', 'canceled')
            ->pluck('id');

        $target = DB::table('stock_mutations')
            ->where('source_type', 'transfer')
            ->where('is_void', true)
            ->whereIn('source_id', $canceledTransferIds);
        $targetCount = (clone $target)->count();
        $existingReversals = DB::table('stock_mutations')
            ->where('source_type', 'transfer_cancel')
            ->whereIn('source_id', $canceledTransferIds)
            ->count();
        $affectedPairs = (clone $target)
            ->select('item_id', 'warehouse_id')
            ->distinct()
            ->count();

        $this->table(['Pemeriksaan', 'Nilai'], [
            ['Mutasi transfer canceled yang masih void', $targetCount],
            ['Mutasi pembalik yang sudah ada', $existingReversals],
            ['Item-gudang terdampak', $affectedPairs],
            ['Mode', $this->option('execute') ? 'EXECUTE' : 'DRY RUN'],
        ]);

        if (!$this->option('execute')) {
            $this->comment('Dry run selesai. Jalankan ulang dengan --execute untuk menerapkan.');
            return self::SUCCESS;
        }

        if ($targetCount === 0) {
            $this->info('Tidak ada mutasi void yang perlu direkonsiliasi.');
            return self::SUCCESS;
        }

        $expected = $this->option('expected');
        if ($expected !== null && $expected !== '' && (int) $expected !== $targetCount) {
            $this->error("Pengaman aktif: target {$targetCount}, sedangkan --expected={$expected}. Tidak ada perubahan dibuat.");
            return self::FAILURE;
        }

        $processed = 0;
        $lastId = 0;
        do {
            $rows = DB::table('stock_mutations')
                ->where('source_type', 'transfer')
                ->where('is_void', true)
                ->whereIn('source_id', $canceledTransferIds)
                ->where('id', '>', $lastId)
                ->orderBy('id')
                ->limit($batchSize)
                ->get();

            if ($rows->isEmpty()) {
                break;
            }

            DB::transaction(function () use ($rows, &$processed) {
                $transferIds = $rows->pluck('source_id')->unique()->values();
                $transfers = DB::table('stock_transfers')
                    ->whereIn('id', $transferIds)
                    ->get(['id', 'code', 'updated_at'])
                    ->keyBy('id');
                $pairs = [];

                foreach ($rows as $original) {
                    $transfer = $transfers->get($original->source_id);
                    if (!$transfer) {
                        throw new \RuntimeException("Transfer {$original->source_id} tidak ditemukan.");
                    }

                    $at = $transfer->updated_at;
                    DB::table('stock_mutations')->updateOrInsert([
                        'item_id' => $original->item_id,
                        'warehouse_id' => $original->warehouse_id,
                        'source_type' => 'transfer_cancel',
                        'source_id' => $original->source_id,
                    ], [
                        'reference_item_id' => $original->reference_item_id,
                        'reference_sku' => $original->reference_sku,
                        'direction' => $original->direction === 'out' ? 'in' : 'out',
                        'qty' => $original->qty,
                        'stock_before' => 0,
                        'stock_after' => 0,
                        'source_subtype' => 'rollback',
                        'source_code' => $transfer->code ?: $original->source_code,
                        'note' => 'Pembalik transfer canceled: '.($transfer->code ?: $original->source_code),
                        'occurred_at' => $at,
                        'created_by' => null,
                        'is_void' => false,
                        'voided_at' => null,
                        'voided_by' => null,
                        'created_at' => $at,
                        'updated_at' => $at,
                    ]);

                    DB::table('stock_mutations')->where('id', $original->id)->update([
                        'is_void' => false,
                        'voided_at' => null,
                        'voided_by' => null,
                    ]);
                    $pairs[$original->item_id.'|'.$original->warehouse_id] = [
                        'item_id' => (int) $original->item_id,
                        'warehouse_id' => (int) $original->warehouse_id,
                    ];
                    $processed++;
                }

                foreach ($pairs as $pair) {
                    $this->recalculatePairSnapshots($pair['item_id'], $pair['warehouse_id']);
                }
            }, 3);

            $lastId = (int) $rows->last()->id;
            $this->line("Diproses: {$processed}/{$targetCount}");
        } while (true);

        $remaining = DB::table('stock_mutations')
            ->where('source_type', 'transfer')
            ->where('is_void', true)
            ->whereIn('source_id', $canceledTransferIds)
            ->count();
        $reversals = DB::table('stock_mutations')
            ->where('source_type', 'transfer_cancel')
            ->whereIn('source_id', $canceledTransferIds)
            ->count();

        $this->table(['Hasil', 'Nilai'], [
            ['Mutasi asal direkonsiliasi', $processed],
            ['Mutasi void tersisa', $remaining],
            ['Mutasi pembalik tersedia', $reversals],
            ['item_stocks diubah oleh command', '0'],
        ]);

        return $remaining === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function recalculatePairSnapshots(int $itemId, int $warehouseId): void
    {
        $balance = (int) (DB::table('item_stocks')
            ->where('item_id', $itemId)
            ->where('warehouse_id', $warehouseId)
            ->value('stock') ?? 0);

        $mutations = DB::table('stock_mutations')
            ->where('item_id', $itemId)
            ->where('warehouse_id', $warehouseId)
            ->where('is_void', false)
            ->orderByDesc('occurred_at')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get(['id', 'direction', 'qty']);

        foreach ($mutations as $mutation) {
            $after = $balance;
            $before = $mutation->direction === 'in'
                ? $after - (int) $mutation->qty
                : $after + (int) $mutation->qty;
            DB::table('stock_mutations')->where('id', $mutation->id)->update([
                'stock_before' => $before,
                'stock_after' => $after,
            ]);
            $balance = $before;
        }
    }
}
