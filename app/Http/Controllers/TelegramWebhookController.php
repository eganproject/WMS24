<?php

namespace App\Http\Controllers;

use App\Support\TelegramBotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request, TelegramBotService $telegramBot): JsonResponse
    {
        $configuredSecret = (string) config('services.telegram.webhook_secret');
        if ($configuredSecret !== '') {
            $incomingSecret = (string) $request->header('X-Telegram-Bot-Api-Secret-Token');
            if (!hash_equals($configuredSecret, $incomingSecret)) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }
        }

        $telegramBot->handleUpdate($request->all());

        return response()->json(['ok' => true]);
    }
}
