<?php

namespace App\Support;

use App\Models\Item;
use App\Models\Warehouse;
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

        return implode("\n", [
            'Command belum dikenali.',
            'Gunakan: /stok SKU',
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
