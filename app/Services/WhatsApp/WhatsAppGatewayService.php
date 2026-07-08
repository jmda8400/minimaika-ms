<?php

namespace App\Services\WhatsApp;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class WhatsAppGatewayService
{
    public function status(): array
    {
        return $this->request()->get($this->url('instance/connectionState'))->throw()->json() ?? [];
    }

    public function qr(): array
    {
        return $this->request()->get($this->url('instance/connect'))->throw()->json() ?? [];
    }

    public function sendText(string $phone, string $text): void
    {
        $this->request()
            ->post($this->url('message/sendText'), [
                'number' => $phone,
                'text' => $text,
            ])
            ->throw();
    }

    private function request(): PendingRequest
    {
        $apiKey = (string) config('services.whatsapp_web.api_key');

        if ($apiKey === '') {
            throw new RuntimeException('WHATSAPP_WEB_API_KEY no está configurado.');
        }

        return Http::withHeaders(['apikey' => $apiKey])
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('services.whatsapp_web.timeout', 15));
    }

    private function url(string $path): string
    {
        $baseUrl = rtrim((string) config('services.whatsapp_web.base_url'), '/');
        $instance = trim((string) config('services.whatsapp_web.instance'));

        if ($baseUrl === '' || $instance === '') {
            Log::error('Gateway WhatsApp Web incompleto.', [
                'base_url_configured' => $baseUrl !== '',
                'instance_configured' => $instance !== '',
            ]);

            throw new RuntimeException('WHATSAPP_WEB_BASE_URL o WHATSAPP_WEB_INSTANCE no configurado.');
        }

        return sprintf('%s/%s/%s', $baseUrl, trim($path, '/'), rawurlencode($instance));
    }
}
