<?php

namespace App\Support;

use App\Models\CustomerReturn;
use App\Models\DamagedGood;
use App\Models\DamagedGoodItem;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\Kurir;
use App\Models\PickingList;
use App\Models\PickingListException;
use App\Models\QcResiScan;
use App\Models\Resi;
use App\Models\ShipmentScanOut;
use App\Models\StockMutation;
use App\Models\Warehouse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class TelegramBotService
{
    public function handleUpdate(array $update): void
    {
        $message = $update['message'] ?? $update['edited_message'] ?? null;
        if (!is_array($message)) {
            return;
        }

        $chatId = $message['chat']['id'] ?? null;
        if (!$chatId || !$this->isAllowedChat($chatId)) {
            return;
        }

        $text = trim((string) ($message['text'] ?? ''));
        if ($text === '') {
            return;
        }

        $this->sendMessage((string) $chatId, $this->replyForText($text));
    }

    public function replyForText(string $text): string
    {
        $text = trim($text);

        if (preg_match('/^\/?(info|help|start|bantuan)(?:@\w+)?$/iu', $text)) {
            return $this->infoReply();
        }

        if (preg_match('/^\/?(stok|stock)(?:@\w+)?\s+(.+)$/iu', $text, $matches)) {
            return $this->stockReply(trim((string) $matches[2]));
        }

        if (preg_match('/^\/?(resi|summary_resi|resi_hari_ini)(?:@\w+)?(?:\s+(.+))?$/iu', $text, $matches)) {
            return $this->todayResiReply(trim((string) ($matches[2] ?? '')));
        }

        if (preg_match('/^\/?(cekresi|resi_detail)(?:@\w+)?\s+(.+)$/iu', $text, $matches)) {
            return $this->resiDetailReply(trim((string) $matches[2]));
        }

        if (preg_match('/^\/?(lowstock|stok_kritis)(?:@\w+)?(?:\s+(.+))?$/iu', $text, $matches)) {
            return $this->lowStockReply(trim((string) ($matches[2] ?? '')));
        }

        if (preg_match('/^\/?(today|dashboard)(?:@\w+)?(?:\s+(.+))?$/iu', $text, $matches)) {
            return $this->todayDashboardReply(trim((string) ($matches[2] ?? '')));
        }

        if (preg_match('/^\/?(lokasi|location)(?:@\w+)?\s+(.+)$/iu', $text, $matches)) {
            return $this->locationReply(trim((string) $matches[2]));
        }

        if (preg_match('/^\/?(mutasi|mutation)(?:@\w+)?\s+(.+)$/iu', $text, $matches)) {
            return $this->stockMutationReply(trim((string) $matches[2]));
        }

        if (preg_match('/^\/?(qc)(?:@\w+)?(?:\s+(.+))?$/iu', $text, $matches)) {
            return $this->qcReply(trim((string) ($matches[2] ?? '')));
        }

        if (preg_match('/^\/?(scanout|scan_out)(?:@\w+)?(?:\s+(.+))?$/iu', $text, $matches)) {
            return $this->scanOutReply(trim((string) ($matches[2] ?? '')));
        }

        if (preg_match('/^\/?(picking|pickinglist)(?:@\w+)?(?:\s+(.+))?$/iu', $text, $matches)) {
            return $this->pickingReply(trim((string) ($matches[2] ?? '')));
        }

        if (preg_match('/^\/?(return|retur)(?:@\w+)?(?:\s+(.+))?$/iu', $text, $matches)) {
            return $this->customerReturnReply(trim((string) ($matches[2] ?? '')));
        }

        if (preg_match('/^\/?(damaged|barang_rusak)(?:@\w+)?(?:\s+(.+))?$/iu', $text, $matches)) {
            return $this->damagedGoodsReply(trim((string) ($matches[2] ?? '')));
        }

        return implode("\n", [
            'Command belum dikenali.',
            'Ketik /info untuk melihat cara penggunaan bot.',
        ]);
    }

    private function infoReply(): string
    {
        return implode("\n", [
            'Panduan Telegram Bot WMS',
            '',
            'Stok dan item:',
            '/stok SKU - cek stok SKU per gudang',
            '/lokasi SKU - cek area, rak/lokasi, dan stok SKU',
            '/lowstock - daftar stok di bawah safety stock',
            '/lowstock NAMA_GUDANG - stok kritis per gudang',
            '/mutasi SKU - 10 mutasi stok terakhir',
            '',
            'Resi dan outbound:',
            '/resi - ringkasan resi hari ini',
            '/resi YYYY-MM-DD - ringkasan resi tanggal tertentu',
            '/cekresi NO_RESI - detail status resi, QC, scan out, dan item',
            '/scanout - ringkasan scan out hari ini',
            '/scanout NAMA_KURIR - scan out hari ini per kurir',
            '',
            'Operasional:',
            '/today - dashboard operasional hari ini',
            '/today YYYY-MM-DD - dashboard tanggal tertentu',
            '/qc - ringkasan QC hari ini',
            '/qc hold - daftar ringkasan QC hold',
            '/picking - ringkasan picking hari ini',
            '/return - ringkasan return customer hari ini',
            '/return KODE_RETURN/NO_RESI - detail return customer',
            '/damaged - ringkasan barang rusak hari ini',
            '',
            'Contoh:',
            '/stok ADA1',
            '/cekresi JNT123456789',
            '/lowstock Gudang Besar',
        ]);
    }

    private function stockReply(string $query): string
    {
        $query = trim($query);
        if ($query === '') {
            return 'Format salah. Gunakan: /stok SKU';
        }

        $item = Item::query()
            ->whereRaw('LOWER(sku) = ?', [mb_strtolower($query)])
            ->with('stocks.warehouse')
            ->first();

        if (!$item) {
            $matches = Item::query()
                ->where('sku', 'like', "%{$query}%")
                ->orWhere('name', 'like', "%{$query}%")
                ->orderBy('sku')
                ->limit(5)
                ->get(['sku', 'name']);

            if ($matches->isEmpty()) {
                return "SKU '{$query}' tidak ditemukan.";
            }

            return "SKU '{$query}' tidak ditemukan persis.\nMungkin maksudnya:\n"
                .$matches->map(fn (Item $row) => '- '.$row->sku.' - '.$row->name)->implode("\n");
        }

        if ($item->isBundle()) {
            return $this->bundleStockReply($item);
        }

        return $this->singleItemStockReply($item);
    }

    private function singleItemStockReply(Item $item): string
    {
        $warehouses = Warehouse::query()
            ->orderBy('name')
            ->get(['id', 'name', 'type'])
            ->sortBy(fn (Warehouse $warehouse) => match ((string) $warehouse->type) {
                'main' => '1-'.$warehouse->name,
                'display' => '2-'.$warehouse->name,
                'damaged' => '3-'.$warehouse->name,
                default => '9-'.$warehouse->name,
            });

        $stocks = $item->stocks->keyBy('warehouse_id');
        $lines = [
            'Stok SKU',
            $item->sku.' - '.$item->name,
            '',
        ];

        $total = 0;
        foreach ($warehouses as $warehouse) {
            $qty = (int) ($stocks->get($warehouse->id)?->stock ?? 0);
            $total += $qty;
            $lines[] = $warehouse->name.': '.$qty.' pcs';
        }

        $lines[] = '';
        $lines[] = 'Total: '.$total.' pcs';

        return implode("\n", $lines);
    }

    private function todayResiReply(string $dateText = ''): string
    {
        $date = $this->parseDateOrToday($dateText);

        $activeResiQuery = Resi::query()
            ->whereDate('tanggal_upload', $date)
            ->where(function ($query) {
                $query->whereNull('status')
                    ->orWhere('status', '!=', 'canceled');
            });

        $activeResiIds = (clone $activeResiQuery)->pluck('id');
        $activeTotal = $activeResiIds->count();
        $canceledTotal = Resi::query()
            ->whereDate('tanggal_upload', $date)
            ->where('status', 'canceled')
            ->count();
        $scanOutToday = ShipmentScanOut::query()
            ->whereDate('scan_date', $date)
            ->count();
        $activeScanOutDone = $activeTotal > 0
            ? ShipmentScanOut::query()
                ->whereIn('resi_id', $activeResiIds)
                ->distinct('resi_id')
                ->count('resi_id')
            : 0;
        $qcPassed = $activeTotal > 0
            ? QcResiScan::query()
                ->whereIn('resi_id', $activeResiIds)
                ->where('status', 'passed')
                ->count()
            : 0;
        $qcInProgress = $activeTotal > 0
            ? QcResiScan::query()
                ->whereIn('resi_id', $activeResiIds)
                ->where('status', '!=', 'passed')
                ->count()
            : 0;
        $pendingQc = max(0, $activeTotal - $qcPassed - $qcInProgress);
        $readyScanOut = max(0, $qcPassed - $activeScanOutDone);
        $remainingScanOut = max(0, $activeTotal - $activeScanOutDone);

        $lines = [
            'Ringkasan Resi Hari Ini',
            Carbon::parse($date)->format('Y-m-d'),
            '',
            'Resi aktif upload hari ini: '.$activeTotal,
            'Cancel: '.$canceledTotal,
            'Scan out hari ini: '.$scanOutToday,
            'Belum scan out dari upload hari ini: '.$remainingScanOut,
            '',
            'Status proses:',
            '- Menunggu QC: '.$pendingQc,
            '- QC berjalan: '.$qcInProgress,
            '- Siap scan out: '.$readyScanOut,
            '- Scan out selesai: '.$activeScanOutDone,
        ];

        $kurirLines = $this->todayResiByCourierLines($date);
        if (!empty($kurirLines)) {
            $lines[] = '';
            $lines[] = 'Per kurir:';
            array_push($lines, ...$kurirLines);
        }

        return implode("\n", $lines);
    }

    private function todayResiByCourierLines(string $date): array
    {
        $resiCounts = Resi::query()
            ->selectRaw('kurir_id, COUNT(*) as total')
            ->whereDate('tanggal_upload', $date)
            ->where(function ($query) {
                $query->whereNull('status')
                    ->orWhere('status', '!=', 'canceled');
            })
            ->groupBy('kurir_id')
            ->pluck('total', 'kurir_id');

        if ($resiCounts->isEmpty()) {
            return [];
        }

        $scanCounts = ShipmentScanOut::query()
            ->selectRaw('kurir_id, COUNT(*) as total')
            ->whereDate('scan_date', $date)
            ->groupBy('kurir_id')
            ->pluck('total', 'kurir_id');

        $kurirNames = Kurir::query()
            ->whereIn('id', $resiCounts->keys()->merge($scanCounts->keys())->filter()->unique()->all())
            ->pluck('name', 'id');

        return $resiCounts
            ->map(function ($total, $kurirId) use ($scanCounts, $kurirNames) {
                $scanned = (int) ($scanCounts[$kurirId] ?? 0);
                $remaining = max(0, (int) $total - $scanned);
                $name = $kurirNames[$kurirId] ?? 'Tanpa Kurir';

                return "- {$name}: {$total} resi, scan out {$scanned}, sisa {$remaining}";
            })
            ->values()
            ->all();
    }

    private function parseDateOrToday(string $dateText): string
    {
        $dateText = trim($dateText);
        if ($dateText === '' || in_array(mb_strtolower($dateText), ['hari_ini', 'hari ini', 'today'], true)) {
            return now()->toDateString();
        }

        try {
            return Carbon::parse($dateText)->toDateString();
        } catch (\Throwable) {
            return now()->toDateString();
        }
    }

    private function bundleStockReply(Item $item): string
    {
        $defaultId = WarehouseService::defaultWarehouseId();
        $displayId = WarehouseService::displayWarehouseId();
        $defaultLabel = Warehouse::where('id', $defaultId)->value('name') ?? 'Gudang Besar';
        $displayLabel = Warehouse::where('id', $displayId)->value('name') ?? 'Gudang Display';
        $defaultQty = BundleService::virtualAvailableQty($item, $defaultId);
        $displayQty = BundleService::virtualAvailableQty($item, $displayId);

        return implode("\n", [
            'Stok Virtual Bundle',
            $item->sku.' - '.$item->name,
            '',
            $defaultLabel.': '.$defaultQty.' set',
            $displayLabel.': '.$displayQty.' set',
            '',
            'Total virtual: '.($defaultQty + $displayQty).' set',
        ]);
    }

    private function resiDetailReply(string $query): string
    {
        if ($query === '') {
            return 'Format salah. Gunakan: /cekresi NO_RESI';
        }

        $resi = Resi::query()
            ->where('no_resi', $query)
            ->orWhere('id_pesanan', $query)
            ->with(['kurir', 'details', 'qcScan.items', 'qcScan.scanner', 'qcScan.completer', 'scanOut.scanner'])
            ->first();

        if (!$resi) {
            return "Resi '{$query}' tidak ditemukan.";
        }

        $status = ResiOperationalStatus::label($resi->operational_status);
        $qc = $resi->qcScan;
        $scanOut = $resi->scanOut;
        $lines = [
            'Detail Resi',
            $resi->no_resi.' / '.$resi->id_pesanan,
            '',
            'Kurir: '.($resi->kurir?->name ?? '-'),
            'Tanggal upload: '.$this->formatDate($resi->tanggal_upload),
            'Status bisnis: '.($resi->status ?: 'active'),
            'Status operasional: '.$status,
            'QC: '.($qc ? ($qc->status ?? 'draft') : 'belum QC'),
        ];

        if ($qc) {
            $lines[] = 'QC mulai: '.$this->formatDateTime($qc->started_at);
            $lines[] = 'QC selesai: '.$this->formatDateTime($qc->completed_at);
            $lines[] = 'QC oleh: '.($qc->completer?->name ?? $qc->scanner?->name ?? '-');
            if ($qc->hold_reason) {
                $lines[] = 'Alasan hold: '.$qc->hold_reason;
            }
        }

        $lines[] = 'Scan out: '.($scanOut ? $this->formatDateTime($scanOut->scanned_at) : 'belum scan out');
        if ($scanOut) {
            $lines[] = 'Scan out oleh: '.($scanOut->scanner?->name ?? '-');
        }

        if ($resi->details->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'Item resi:';
            foreach ($resi->details->take(10) as $detail) {
                $lines[] = '- '.$detail->sku.': '.(int) $detail->qty.' pcs';
            }
            if ($resi->details->count() > 10) {
                $lines[] = '- ... '.($resi->details->count() - 10).' item lain';
            }
        }

        if ($qc?->items?->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'Progress QC item:';
            foreach ($qc->items->take(10) as $item) {
                $lines[] = '- '.$item->sku.': '.(int) $item->scanned_qty.'/'.(int) $item->expected_qty;
            }
        }

        return implode("\n", $lines);
    }

    private function lowStockReply(string $warehouseText = ''): string
    {
        $warehouseText = trim($warehouseText);
        $query = ItemStock::query()
            ->with(['item.location.area', 'item.area', 'warehouse'])
            ->whereHas('item')
            ->join('items', 'items.id', '=', 'item_stocks.item_id')
            ->select('item_stocks.*')
            ->whereRaw('item_stocks.stock < COALESCE(item_stocks.safety_stock, items.safety_stock, 0)')
            ->whereRaw('COALESCE(item_stocks.safety_stock, items.safety_stock, 0) > 0');

        if ($warehouseText !== '') {
            $query->whereHas('warehouse', function ($warehouseQuery) use ($warehouseText) {
                $warehouseQuery->where('name', 'like', "%{$warehouseText}%")
                    ->orWhere('code', 'like', "%{$warehouseText}%");
            });
        }

        $rows = $query
            ->orderByRaw('(COALESCE(item_stocks.safety_stock, items.safety_stock, 0) - item_stocks.stock) DESC')
            ->limit(15)
            ->get();

        if ($rows->isEmpty()) {
            return $warehouseText === ''
                ? 'Tidak ada stok kritis.'
                : "Tidak ada stok kritis untuk gudang '{$warehouseText}'.";
        }

        $lines = [
            'Stok Kritis'.($warehouseText !== '' ? ' - '.$warehouseText : ''),
            '',
        ];

        foreach ($rows as $row) {
            $threshold = (int) ($row->safety_stock ?? $row->item?->safety_stock ?? 0);
            $location = $row->item?->resolvedAddress();
            $lines[] = '- '.$row->item?->sku.' | '.$row->warehouse?->name.': '.(int) $row->stock.'/'.$threshold.' pcs'
                .($location ? ' | Lokasi '.$location : '');
        }

        return implode("\n", $lines);
    }

    private function todayDashboardReply(string $dateText = ''): string
    {
        $date = $this->parseDateOrToday($dateText);
        $activeResi = $this->activeResiQuery($date);
        $activeResiIds = (clone $activeResi)->pluck('id');
        $qcPassed = $activeResiIds->isNotEmpty()
            ? QcResiScan::whereIn('resi_id', $activeResiIds)->where('status', 'passed')->count()
            : 0;
        $scanOutDone = $activeResiIds->isNotEmpty()
            ? ShipmentScanOut::whereIn('resi_id', $activeResiIds)->distinct('resi_id')->count('resi_id')
            : 0;

        $returns = CustomerReturn::query()
            ->whereDate('received_at', $date)
            ->count();
        $returnsCompleted = CustomerReturn::query()
            ->whereDate('received_at', $date)
            ->where('status', CustomerReturn::STATUS_COMPLETED)
            ->count();
        $damagedDocs = DamagedGood::query()
            ->whereDate('transacted_at', $date)
            ->count();
        $damagedQty = DamagedGoodItem::query()
            ->whereHas('damagedGood', fn ($query) => $query->whereDate('transacted_at', $date))
            ->sum('qty');
        $pickingQty = PickingList::query()->whereDate('list_date', $date)->sum('qty');
        $pickingRemaining = PickingList::query()->whereDate('list_date', $date)->sum('remaining_qty');
        $exceptions = PickingListException::query()->whereDate('list_date', $date)->sum('qty');

        $lines = [
            'Dashboard Hari Ini',
            Carbon::parse($date)->format('Y-m-d'),
            '',
            'Resi aktif: '.(clone $activeResi)->count(),
            'QC passed: '.$qcPassed,
            'Scan out selesai: '.$scanOutDone,
            'Belum scan out: '.max(0, (clone $activeResi)->count() - $scanOutDone),
            '',
            'Picking qty: '.(int) $pickingQty,
            'Sisa picking: '.(int) $pickingRemaining,
            'Exception picking: '.(int) $exceptions,
            '',
            'Return customer: '.$returns.' dokumen, selesai '.$returnsCompleted,
            'Barang rusak: '.$damagedDocs.' dokumen, '.(int) $damagedQty.' pcs',
        ];

        $kurirLines = $this->todayResiByCourierLines($date);
        if (!empty($kurirLines)) {
            $lines[] = '';
            $lines[] = 'Per kurir:';
            array_push($lines, ...array_slice($kurirLines, 0, 8));
        }

        return implode("\n", $lines);
    }

    private function locationReply(string $query): string
    {
        $item = $this->findItemForQuery($query, ['category', 'location.area', 'area', 'stocks.warehouse']);
        if (!$item) {
            return "SKU atau item '{$query}' tidak ditemukan.";
        }

        $lines = [
            'Lokasi SKU',
            $item->sku.' - '.$item->name,
            '',
            'Kategori: '.($item->category?->name ?? '-'),
            'Area: '.($item->resolvedArea()?->name ?? '-'),
            'Lokasi: '.($item->resolvedAddress() ?: '-'),
            'Koli: '.((int) ($item->koli_qty ?? 0) ?: '-'),
            '',
            'Stok:',
        ];

        foreach ($item->stocks->sortBy(fn ($stock) => $stock->warehouse?->name ?? '') as $stock) {
            $lines[] = '- '.$stock->warehouse?->name.': '.(int) $stock->stock.' pcs';
        }

        return implode("\n", $lines);
    }

    private function stockMutationReply(string $query): string
    {
        $item = $this->findItemForQuery($query);
        if (!$item) {
            return "SKU atau item '{$query}' tidak ditemukan.";
        }

        $mutations = StockMutation::query()
            ->where(function ($mutationQuery) use ($item) {
                $mutationQuery->where('item_id', $item->id)
                    ->orWhere('reference_item_id', $item->id)
                    ->orWhere('reference_sku', $item->sku);
            })
            ->with(['warehouse'])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        if ($mutations->isEmpty()) {
            return 'Belum ada mutasi untuk '.$item->sku.'.';
        }

        $lines = [
            'Mutasi Stok',
            $item->sku.' - '.$item->name,
            '',
        ];

        foreach ($mutations as $mutation) {
            $sign = $mutation->direction === 'in' ? '+' : '-';
            $lines[] = '- '.$this->formatDateTime($mutation->occurred_at).' | '
                .$mutation->warehouse?->name.' | '.$sign.(int) $mutation->qty.' | '
                .($mutation->source_code ?: $mutation->source_type ?: '-');
        }

        return implode("\n", $lines);
    }

    private function qcReply(string $text = ''): string
    {
        $text = trim($text);
        $date = $this->parseDateOrToday($text);
        $query = QcResiScan::query()
            ->where(function ($dateQuery) use ($date) {
                $dateQuery->whereDate('started_at', $date)
                    ->orWhereDate('completed_at', $date)
                    ->orWhereDate('created_at', $date);
            });

        if (in_array(mb_strtolower($text), ['hold', 'on_hold'], true)) {
            $query = QcResiScan::query()->where('status', 'hold');
        }

        $total = (clone $query)->count();
        $draft = (clone $query)->where('status', 'draft')->count();
        $hold = (clone $query)->where('status', 'hold')->count();
        $passed = (clone $query)->where('status', 'passed')->count();
        $latest = (clone $query)->with('resi')->orderByDesc('updated_at')->limit(5)->get();

        $lines = [
            'Ringkasan QC'.(!in_array(mb_strtolower($text), ['hold', 'on_hold'], true) ? ' '.$date : ' Hold'),
            '',
            'Total: '.$total,
            'Draft/berjalan: '.$draft,
            'Hold: '.$hold,
            'Passed: '.$passed,
        ];

        if ($latest->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'Terbaru:';
            foreach ($latest as $row) {
                $lines[] = '- '.($row->resi?->no_resi ?? $row->scan_code).': '.($row->status ?? 'draft');
            }
        }

        return implode("\n", $lines);
    }

    private function scanOutReply(string $text = ''): string
    {
        $text = trim($text);
        $date = $this->parseDateOrToday($text);
        $query = ShipmentScanOut::query()->whereDate('scan_date', $date);

        if ($text !== '' && $date === now()->toDateString() && !in_array(mb_strtolower($text), ['hari_ini', 'hari ini', 'today'], true)) {
            $query->whereHas('kurir', function ($kurirQuery) use ($text) {
                $kurirQuery->where('name', 'like', "%{$text}%");
            });
        }

        $total = (clone $query)->count();
        $byCourier = (clone $query)
            ->selectRaw('kurir_id, COUNT(*) as total')
            ->groupBy('kurir_id')
            ->pluck('total', 'kurir_id');
        $kurirNames = Kurir::whereIn('id', $byCourier->keys()->filter()->all())->pluck('name', 'id');

        $lines = [
            'Ringkasan Scan Out',
            $date,
            '',
            'Total scan out: '.$total,
        ];

        if ($byCourier->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'Per kurir:';
            foreach ($byCourier as $kurirId => $count) {
                $lines[] = '- '.($kurirNames[$kurirId] ?? 'Tanpa Kurir').': '.$count;
            }
        }

        return implode("\n", $lines);
    }

    private function pickingReply(string $dateText = ''): string
    {
        $date = $this->parseDateOrToday($dateText);
        $lists = PickingList::query()->whereDate('list_date', $date);
        $exceptions = PickingListException::query()->whereDate('list_date', $date);
        $topRemaining = (clone $lists)
            ->where('remaining_qty', '>', 0)
            ->orderByDesc('remaining_qty')
            ->limit(8)
            ->get(['sku', 'qty', 'remaining_qty']);

        $lines = [
            'Ringkasan Picking',
            $date,
            '',
            'SKU picking: '.(clone $lists)->count(),
            'Total qty: '.(int) (clone $lists)->sum('qty'),
            'Sisa qty: '.(int) (clone $lists)->sum('remaining_qty'),
            'Exception qty: '.(int) (clone $exceptions)->sum('qty'),
        ];

        if ($topRemaining->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'Sisa terbesar:';
            foreach ($topRemaining as $row) {
                $lines[] = '- '.$row->sku.': sisa '.(int) $row->remaining_qty.' dari '.(int) $row->qty;
            }
        }

        return implode("\n", $lines);
    }

    private function customerReturnReply(string $text = ''): string
    {
        $text = trim($text);
        if ($text !== '' && !in_array(mb_strtolower($text), ['today', 'hari_ini', 'hari ini'], true) && !strtotime($text)) {
            $return = CustomerReturn::query()
                ->where('code', $text)
                ->orWhere('resi_no', $text)
                ->orWhere('order_ref', $text)
                ->with(['items.item', 'resi'])
                ->first();

            if (!$return) {
                return "Return '{$text}' tidak ditemukan.";
            }

            $lines = [
                'Detail Return Customer',
                $return->code,
                '',
                'Resi: '.($return->resi_no ?: $return->resi?->no_resi ?: '-'),
                'Order: '.($return->order_ref ?: '-'),
                'Status: '.($return->status ?: '-'),
                'Diterima: '.$this->formatDateTime($return->received_at),
                'Final: '.$this->formatDateTime($return->finalized_at),
            ];

            if ($return->items->isNotEmpty()) {
                $lines[] = '';
                $lines[] = 'Item:';
                foreach ($return->items->take(10) as $item) {
                    $lines[] = '- '.$item->item?->sku.': terima '.(int) $item->received_qty
                        .', bagus '.(int) $item->good_qty.', rusak '.(int) $item->damaged_qty;
                }
            }

            return implode("\n", $lines);
        }

        $date = $this->parseDateOrToday($text);
        $query = CustomerReturn::query()->whereDate('received_at', $date);
        $total = (clone $query)->count();
        $completed = (clone $query)->where('status', CustomerReturn::STATUS_COMPLETED)->count();
        $inspected = (clone $query)->where('status', CustomerReturn::STATUS_INSPECTED)->count();

        return implode("\n", [
            'Ringkasan Return Customer',
            $date,
            '',
            'Total dokumen: '.$total,
            'Inspected: '.$inspected,
            'Completed: '.$completed,
            'Belum final: '.max(0, $total - $completed),
        ]);
    }

    private function damagedGoodsReply(string $dateText = ''): string
    {
        $date = $this->parseDateOrToday($dateText);
        $docs = DamagedGood::query()->whereDate('transacted_at', $date);
        $items = DamagedGoodItem::query()
            ->whereHas('damagedGood', fn ($query) => $query->whereDate('transacted_at', $date));

        $topSku = (clone $items)
            ->selectRaw('item_id, SUM(qty) as total_qty')
            ->with('item')
            ->groupBy('item_id')
            ->orderByDesc('total_qty')
            ->limit(8)
            ->get();

        $lines = [
            'Ringkasan Barang Rusak',
            $date,
            '',
            'Dokumen: '.(clone $docs)->count(),
            'Approved: '.(clone $docs)->where('status', 'approved')->count(),
            'Pending: '.(clone $docs)->where(function ($query) {
                $query->whereNull('status')->orWhere('status', 'pending');
            })->count(),
            'Total qty rusak: '.(int) (clone $items)->sum('qty'),
        ];

        if ($topSku->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'Top SKU rusak:';
            foreach ($topSku as $row) {
                $lines[] = '- '.$row->item?->sku.': '.(int) $row->total_qty.' pcs';
            }
        }

        return implode("\n", $lines);
    }

    private function findItemForQuery(string $query, array $with = []): ?Item
    {
        $query = trim($query);
        if ($query === '') {
            return null;
        }

        $itemQuery = Item::query();
        if (!empty($with)) {
            $itemQuery->with($with);
        }

        $item = (clone $itemQuery)
            ->whereRaw('LOWER(sku) = ?', [mb_strtolower($query)])
            ->first();

        if ($item) {
            return $item;
        }

        return $itemQuery
            ->where('sku', 'like', "%{$query}%")
            ->orWhere('name', 'like', "%{$query}%")
            ->orderBy('sku')
            ->first();
    }

    private function activeResiQuery(string $date)
    {
        return Resi::query()
            ->whereDate('tanggal_upload', $date)
            ->where(function ($query) {
                $query->whereNull('status')
                    ->orWhere('status', '!=', 'canceled');
            });
    }

    private function formatDate($value): string
    {
        return $value ? Carbon::parse($value)->format('Y-m-d') : '-';
    }

    private function formatDateTime($value): string
    {
        return $value ? Carbon::parse($value)->format('Y-m-d H:i') : '-';
    }

    private function isAllowedChat(string|int $chatId): bool
    {
        $allowedChatIds = config('services.telegram.allowed_chat_ids', []);
        if (empty($allowedChatIds)) {
            return true;
        }

        return in_array((string) $chatId, array_map('strval', $allowedChatIds), true);
    }

    public function notifyAllowedChats(string $text): int
    {
        $chatIds = array_filter(array_map('strval', config('services.telegram.allowed_chat_ids', [])));
        if (empty($chatIds)) {
            return 0;
        }

        foreach ($chatIds as $chatId) {
            $this->sendMessage($chatId, $text);
        }

        return count($chatIds);
    }

    private function sendMessage(string $chatId, string $text): void
    {
        $token = (string) config('services.telegram.bot_token');
        if ($token === '') {
            return;
        }

        Http::asJson()
            ->timeout(10)
            ->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => Str::limit($text, 3900, "\n..."),
            ]);
    }
}
