<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TelegramSetWebhook extends Command
{
    protected $signature = 'telegram:set-webhook
        {url? : URL publik endpoint webhook Telegram}
        {--drop-pending : Hapus update lama yang belum diproses Telegram}';

    protected $description = 'Daftarkan endpoint webhook aplikasi ke Telegram Bot API.';

    public function handle(): int
    {
        $token = (string) config('services.telegram.bot_token');
        if ($token === '') {
            $this->error('TELEGRAM_BOT_TOKEN belum diisi.');

            return self::FAILURE;
        }

        $url = (string) ($this->argument('url') ?: url('/telegram/webhook'));
        if (!str_starts_with($url, 'https://')) {
            $this->error('Telegram membutuhkan URL webhook HTTPS publik.');

            return self::FAILURE;
        }

        $payload = [
            'url' => $url,
            'drop_pending_updates' => (bool) $this->option('drop-pending'),
        ];

        $secret = (string) config('services.telegram.webhook_secret');
        if ($secret !== '') {
            $payload['secret_token'] = $secret;
        }

        $response = Http::asJson()
            ->timeout(15)
            ->post("https://api.telegram.org/bot{$token}/setWebhook", $payload);

        if (!$response->successful() || !$response->json('ok')) {
            $this->error('Gagal set webhook Telegram.');
            $this->line($response->body());

            return self::FAILURE;
        }

        $this->info('Webhook Telegram berhasil diset.');
        $this->line('URL: '.$url);

        return self::SUCCESS;
    }
}
