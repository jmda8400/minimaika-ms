<?php

namespace App\Http\Controllers\Bot;

use App\Http\Controllers\Controller;
use App\Services\Bot\BotSettingsService;
use App\Services\WhatsApp\WhatsAppGatewayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class BotSettingsController extends Controller
{
    public function __construct(
        private readonly BotSettingsService $settingsService,
        private readonly WhatsAppGatewayService $whatsAppGatewayService,
    ) {
    }

    public function edit(): View
    {
        return view('bot.settings', [
            'settings' => $this->settingsService->get(),
            'whatsApp' => $this->whatsAppState(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'response_mode' => ['required', 'in:bot,default'],
            'default_message' => ['required', 'string', 'max:1000'],
            'respond_to_groups' => ['nullable', 'boolean'],
        ]);

        $this->settingsService->save([
            'response_mode' => $validated['response_mode'],
            'default_message' => $validated['default_message'],
            'respond_to_groups' => $request->boolean('respond_to_groups'),
            'use_generative_ai' => true,
            // These legacy keys remain in storage for backwards compatibility,
            // but fallbacks are intentionally never forwarded as another message.
            'notify_on_fallback' => false,
            'fallback_alert_phone' => '',
        ]);

        return redirect()->route('bot.settings.edit')->with('status', 'Configuración guardada.');
    }

    public function forgetSession(): RedirectResponse
    {
        try {
            $this->whatsAppGatewayService->logout();

            return redirect()->route('bot.settings.edit')->with('status', 'Sesión de WhatsApp olvidada. QR nuevo disponible para reconexión.');
        } catch (Throwable) {
            return redirect()->route('bot.settings.edit')->with('status', 'No se pudo olvidar la sesión de WhatsApp. Gateway no disponible para completar la acción.');
        }
    }

    /**
     * @return array{available:bool,label:string,detail:string|null,connected:bool,payload:array<string,mixed>,qr:array{image:string,code:string,error:string|null}}
     */
    private function whatsAppState(): array
    {
        $payload = [];
        $state = '';
        $available = false;

        try {
            $payload = $this->whatsAppGatewayService->status();
            $state = strtolower((string) (data_get($payload, 'instance.state') ?? data_get($payload, 'state') ?? data_get($payload, 'status') ?? ''));
            $available = true;
        } catch (Throwable) {
            // Keep rendering settings even when the gateway is unavailable.
        }

        $connected = in_array($state, ['open', 'connected', 'online'], true);

        return [
            'available' => $available,
            'label' => $available ? ($connected ? 'Conectado' : 'No conectado') : 'Estado no disponible',
            'detail' => $available ? ($state !== '' ? $state : 'Sin estado informado') : 'No se pudo consultar el gateway de WhatsApp.',
            'connected' => $connected,
            'payload' => $payload,
            'qr' => $connected ? ['image' => '', 'code' => '', 'error' => null] : $this->qrState(),
        ];
    }

    /**
     * @return array{image:string,code:string,error:string|null}
     */
    private function qrState(): array
    {
        try {
            $payload = $this->whatsAppGatewayService->qr();

            return [
                'image' => (string) (data_get($payload, 'base64') ?? data_get($payload, 'qrcode.base64') ?? ''),
                'code' => (string) (data_get($payload, 'code') ?? data_get($payload, 'pairingCode') ?? ''),
                'error' => null,
            ];
        } catch (Throwable) {
            return [
                'image' => '',
                'code' => '',
                'error' => 'No se pudo obtener el QR desde el gateway.',
            ];
        }
    }
}
