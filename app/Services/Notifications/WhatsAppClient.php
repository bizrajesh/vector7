<?php

namespace App\Services\Notifications;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * WhatsApp Cloud API adapter. The base URL is fixed in config (never user input),
 * which rules out SSRF through this client (OWASP A10).
 * Sends the pre-approved template "vector7_alert" with one body parameter.
 */
class WhatsAppClient
{
    public function isConfigured(): bool
    {
        return filled(config('services.whatsapp.token')) && filled(config('services.whatsapp.phone_number_id'));
    }

    public function send(string $phone, string $message): void
    {
        $to = preg_replace('/\D/', '', $phone);
        if (strlen($to) === 10) {
            $to = '91'.$to;
        }

        $url = config('services.whatsapp.base_url').'/'.rawurlencode((string) config('services.whatsapp.phone_number_id')).'/messages';

        $response = Http::withToken((string) config('services.whatsapp.token'))->timeout(15)->post($url, [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => 'vector7_alert',
                'language' => ['code' => 'en'],
                'components' => [['type' => 'body', 'parameters' => [['type' => 'text', 'text' => mb_substr($message, 0, 900)]]]],
            ],
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('WhatsApp API returned '.$response->status());
        }
    }
}
