<?php

namespace App\Listeners;

use App\Events\ConversationPausedEvent;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class SendPausedNotificationListener
{
    public function handle(ConversationPausedEvent $event)
    {
        $conversation = $event->conversation;
        $user = $conversation->user;

        $cacheKey = "paused_notification_sent_{$conversation->id}";

        if (Cache::has($cacheKey)) {
            Log::info('Duplicate notification prevented', ['conversation_id' => $conversation->id]);
            return;
        }

        $message = "⏸️ *Conversación pausada.*\n\n" .
                   "Hola {$user->name}, la conversación con el usuario {$conversation->customer_phone} fue pausada para que la retomes.\n\n" .
                   "Click aquí: https://wa.me/{$conversation->customer_phone}";

        $sent = $this->sendWhatsAppMessage($user->phone, $message);

        if ($sent) {
            Cache::put($cacheKey, true, now()->addMinutes(5));
        }
    }

    private function sendWhatsAppMessage($phone, $text)
    {
        $evolutionConfig = $this->getEvolutionConfig();
        $serverUrl = $evolutionConfig['base_url'] . '/message/sendText/e_ai_1';
        $apiKey = $evolutionConfig['api_key'];

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'apikey' => $apiKey,
        ])->post($serverUrl, [
            'number' => $phone,
            'text' => $text,
            'delay' => 1000,
            'linkPreview' => false,
        ]);

        if ($response->successful()) {
            Log::info('WhatsApp message sent successfully', ['phone' => $phone]);
            return true;
        } else {
            Log::error('Failed to send WhatsApp message', ['phone' => $phone, 'response' => $response->body()]);
            return false;
        }
    }

    private function getEvolutionConfig()
    {
        $baseUrl = DB::table('configurations')->where('key', 'evolution_api_base_url')->value('value');
        $apiKey = DB::table('configurations')->where('key', 'evolution_api_key')->value('value');

        return [
            'base_url' => $baseUrl,
            'api_key' => $apiKey
        ];
    }
}