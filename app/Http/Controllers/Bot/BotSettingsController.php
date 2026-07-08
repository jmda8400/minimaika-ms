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
            'whatsAppStatus' => $this->whatsAppStatus(),
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
        ]);

        return redirect()->route('bot.settings.edit')->with('status', 'Configuración guardada.');
    }

    /**
     * @return array{available:bool,label:string,detail:string|null}
     */
    private function whatsAppStatus(): array
    {
        try {
            $payload = $this->whatsAppGatewayService->status();
            $state = strtolower((string) (data_get($payload, 'instance.state') ?? data_get($payload, 'state') ?? data_get($payload, 'status') ?? ''));

            return [
                'available' => true,
                'label' => in_array($state, ['open', 'connected', 'online'], true) ? 'Conectado' : 'No conectado',
                'detail' => $state !== '' ? $state : 'Sin estado informado',
            ];
        } catch (Throwable) {
            return [
                'available' => false,
                'label' => 'Estado no disponible',
                'detail' => 'No se pudo consultar el gateway de WhatsApp.',
            ];
        }
    }
}
