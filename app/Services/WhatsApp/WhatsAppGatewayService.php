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

    public function logout(): array
    {
        return $this->request()->delete($this->url('instance/logout'))->throw()->json() ?? [];
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

    /** @param array<int,array{id:string,title:string,description:string}> $rows */
    public function sendMenu(string $phone, string $title, string $button, array $rows): void
    {
        $groups = array_chunk($rows, 3);

        foreach ($groups as $index => $group) {
            $response = $this->request()->post($this->url('message/sendButtons'), [
                'number' => $phone,
                'title' => $index === 0 ? $title : sprintf('%s (%d/%d)', $button, $index + 1, count($groups)),
                'description' => 'Tocá una opción para continuar.',
                'footer' => 'Refugio Agostino Rocca',
                'buttons' => array_map(static fn (array $row): array => [
                    'type' => 'reply',
                    'displayText' => mb_substr($row['title'], 0, 20),
                    'id' => $row['id'],
                ], $group),
            ]);

            if ($response->failed()) {
                Log::error('El gateway rechazó los botones interactivos de WhatsApp.', [
                    'endpoint' => 'message/sendButtons',
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'instance' => (string) config('services.whatsapp_web.instance'),
                    'buttons' => count($group),
                    'group' => $index + 1,
                    'groups' => count($groups),
                ]);
            }

            $response->throw();
        }
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
