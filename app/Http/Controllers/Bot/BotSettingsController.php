<?php

namespace App\Http\Controllers\Bot;

use App\Http\Controllers\Controller;
use App\Services\Bot\BotSettingsService;
use App\Services\WhatsApp\WhatsAppGatewayService;
use App\Services\WhatsApp\WhatsAppNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class BotSettingsController extends Controller
{
    public function __construct(
        private readonly BotSettingsService $settingsService,
        private readonly WhatsAppGatewayService $whatsAppGatewayService,
        private readonly WhatsAppNotificationService $notifications,
    ) {
    }

    public function edit(): View
    {
        return view('bot.settings', [
            'settings' => $this->settingsService->get(),
            'whatsApp' => $this->whatsAppState(),
            'groups' => $this->groupsState(),
            'notificationStatus' => [
                'last_success' => cache('whatsapp-heartbeat-last-success'),
                'last_error' => cache('whatsapp-heartbeat-last-error'),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'response_mode' => ['required', 'in:bot,default'],
            'default_message' => ['required', 'string', 'max:1000'],
            'respond_to_groups' => ['nullable', 'boolean'],
            'notifications_enabled' => ['nullable', 'boolean'],
            'notification_group_id' => ['nullable', 'required_if:notifications_enabled,1', 'string', 'max:120', 'regex:/^[^\s]+@g\.us$/'],
            'notification_group_name' => ['nullable', 'string', 'max:200'],
            'heartbeat_enabled' => ['nullable', 'boolean'],
            'heartbeat_time' => ['required', 'date_format:H:i'],
            'heartbeat_timezone' => ['required', 'timezone:all'],
            'bot_texts' => ['required', 'array'],
            'bot_texts.welcome' => ['required', 'string', 'max:2000'],
            'bot_texts.main_menu_title' => ['required', 'string', 'max:2000'],
            'bot_texts.menu_button' => ['required', 'string', 'max:2000'],
            'bot_texts.select_prompt' => ['required', 'string', 'max:2000'],
            'bot_texts.follow_up' => ['required', 'string', 'max:2000'],
            'bot_texts.back_title' => ['required', 'string', 'max:2000'],
            'bot_texts.back_description' => ['required', 'string', 'max:2000'],
            'bot_texts.option_description' => ['required', 'string', 'max:2000'],
            'navigation' => ['required', 'array', 'min:1', 'max:30'],
            'navigation.*.id' => ['nullable', 'string', 'max:80'],
            'navigation.*.title' => ['required', 'string', 'max:120'],
            'navigation.*.options' => ['required', 'array', 'min:1', 'max:50'],
            'navigation.*.options.*.id' => ['nullable', 'string', 'max:80'],
            'navigation.*.options.*.title' => ['required', 'string', 'max:120'],
            'navigation.*.options.*.answer' => ['required', 'string', 'max:5000'],
        ]);

        $navigation = $this->normalizeNavigation($validated['navigation']);

        $this->settingsService->save([
            'response_mode' => $validated['response_mode'],
            'default_message' => $validated['default_message'],
            'respond_to_groups' => $request->boolean('respond_to_groups'),
            'notifications_enabled' => $request->boolean('notifications_enabled'),
            'notification_group_id' => $validated['notification_group_id'] ?? '',
            'notification_group_name' => $validated['notification_group_name'] ?? '',
            'heartbeat_enabled' => $request->boolean('heartbeat_enabled'),
            'heartbeat_time' => $validated['heartbeat_time'],
            'heartbeat_timezone' => $validated['heartbeat_timezone'],
            'bot_texts' => $validated['bot_texts'],
            'navigation' => $navigation,
        ]);

        return redirect()->route('bot.settings.edit')->with('status', 'Configuración guardada.');
    }

    /** @param array<int,array<string,mixed>> $navigation */
    private function normalizeNavigation(array $navigation): array
    {
        $used = ['main' => true];
        $uniqueId = function (?string $id, string $title) use (&$used): string {
            $base = Str::slug($id ?: $title, '_') ?: 'opcion';
            $candidate = $base;
            for ($number = 2; isset($used[$candidate]); $number++) {
                $candidate = $base.'_'.$number;
            }
            $used[$candidate] = true;

            return $candidate;
        };

        return array_map(function (array $category) use ($uniqueId): array {
            return [
                'id' => $uniqueId($category['id'] ?? null, $category['title']),
                'title' => trim($category['title']),
                'options' => array_map(fn (array $option): array => [
                    'id' => $uniqueId($option['id'] ?? null, $option['title']),
                    'title' => trim($option['title']),
                    'answer' => trim($option['answer']),
                ], $category['options']),
            ];
        }, $navigation);
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

    public function testNotification(): RedirectResponse
    {
        try {
            $this->notifications->sendTest();

            return redirect()->route('bot.settings.edit')->with('status', 'Mensaje de prueba enviado al grupo de alertas.');
        } catch (Throwable $exception) {
            return redirect()->route('bot.settings.edit')->with('status', 'No se pudo enviar la prueba: '.$exception->getMessage());
        }
    }

    /** @return array{available:bool,items:array<int,array{id:string,name:string}>,error:?string} */
    private function groupsState(): array
    {
        try {
            return ['available' => true, 'items' => $this->whatsAppGatewayService->groups(), 'error' => null];
        } catch (Throwable) {
            return [
                'available' => false,
                'items' => [],
                'error' => 'No se pudo obtener la lista de grupos. Podés ingresar el identificador manualmente.',
            ];
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
