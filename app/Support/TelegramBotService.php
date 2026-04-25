<?php

namespace App\Support;

use App\Models\Item;
use App\Models\Kurir;
use App\Models\QcResiScan;
use App\Models\Resi;
use App\Models\ShipmentScanOut;
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

        if (preg_match('/^\/?(stok|stock)(?:@\w+)?\s+(.+)$/iu', $text, $matches)) {
            return $this->stockReply(trim((string) $matches[2]));
        }

        if (preg_match('/^\/?(resi|summary_resi|resi_hari_ini)(?:@\w+)?(?:\s+(.+))?$/iu', $text, $matches)) {
            return $this->todayResiReply(trim((string) ($matches[2] ?? '')));
        }

        return implode("\n", [
            'Command belum dikenali.',
            'Gunakan: /stok SKU',
            'Ringkasan resi hari ini: /resi',
            'Contoh: /stok ADA1',
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

    private function isAllowedChat(string|int $chatId): bool
    {
        $allowedChatIds = config('services.telegram.allowed_chat_ids', []);
        if (empty($allowedChatIds)) {
            return true;
        }

        return in_array((string) $chatId, array_map('strval', $allowedChatIds), true);
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
