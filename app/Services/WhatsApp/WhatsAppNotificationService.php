<?php

namespace App\Services\WhatsApp;

use App\Services\Bot\BotSettingsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class WhatsAppNotificationService
{
    public function __construct(
        private readonly BotSettingsService $settingsService,
        private readonly WhatsAppGatewayService $gateway,
    ) {
    }

    public function sendClaim(string $selection, string $customerPhone): bool
    {
        $problem = match ($selection) {
            'booking_claim' => 'indica tener problemas con la reserva',
            'missing_voucher' => 'indica no haber recibido el voucher',
            default => null,
        };

        if ($problem === null) {
            return false;
        }

        $phone = preg_replace('/\D+/', '', $customerPhone) ?? '';
        $formattedPhone = $phone === '' ? 'desconocido' : '+'.$phone;

        return $this->sendToConfiguredGroup("⚠️ *Reclamo recibido*\nEl cliente de número de teléfono {$formattedPhone} {$problem}.");
    }

    public function sendHeartbeat(bool $force = false): bool
    {
        $settings = $this->settingsService->get();
        if (! $settings['notifications_enabled'] || ! $settings['heartbeat_enabled']) {
            return false;
        }

        $now = Carbon::now($settings['heartbeat_timezone']);
        if (! $force && $now->format('H:i') !== $settings['heartbeat_time']) {
            return false;
        }

        $sentKey = 'whatsapp-heartbeat-sent:'.$now->format('Y-m-d');
        if (! $force && Cache::has($sentKey)) {
            return false;
        }

        $state = $this->connectionState($this->gateway->status());
        if (! in_array($state, ['open', 'connected', 'online'], true)) {
            Cache::put('whatsapp-heartbeat-last-error', [
                'at' => $now->toIso8601String(),
                'message' => 'La sesión no está conectada (estado: '.($state ?: 'desconocido').').',
            ], now()->addDays(30));
            Log::warning('No se envió el estado de WhatsApp porque la sesión no está conectada.', ['state' => $state]);

            return false;
        }

        $sent = $this->sendToConfiguredGroup(
            "✅ *Bot operativo*\nLa sesión de WhatsApp se encuentra conectada.\nÚltima comprobación: ".$now->format('d/m/Y H:i').' ('.$settings['heartbeat_timezone'].').',
        );

        if ($sent) {
            Cache::put($sentKey, true, now()->addDays(2));
            Cache::put('whatsapp-heartbeat-last-success', $now->toIso8601String(), now()->addDays(30));
            Cache::forget('whatsapp-heartbeat-last-error');
        }

        return $sent;
    }

    public function sendTest(): void
    {
        if (! $this->sendToConfiguredGroup("✅ *Mensaje de prueba*\nEl grupo quedó configurado para recibir alertas del bot.")) {
            throw new RuntimeException('Las notificaciones o el grupo de alertas no están configurados.');
        }
    }

    /** @param array<string,mixed> $payload */
    private function connectionState(array $payload): string
    {
        return strtolower((string) (data_get($payload, 'instance.state') ?? data_get($payload, 'state') ?? data_get($payload, 'status') ?? ''));
    }

    private function sendToConfiguredGroup(string $message): bool
    {
        $settings = $this->settingsService->get();
        $groupId = $settings['notification_group_id'];
        if (! $settings['notifications_enabled'] || ! str_ends_with($groupId, '@g.us')) {
            return false;
        }

        $this->gateway->sendText($groupId, $message);

        return true;
    }
}
