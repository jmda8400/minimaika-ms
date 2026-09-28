<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use App\Jobs\SendWhatsAppClaimAlert;
use App\Services\Bot\BotSettingsService;
use App\Services\Bot\NavigationTreeService;
use App\Services\WhatsApp\WhatsAppGatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Throwable;

class WhatsAppWebhookController extends Controller
{
    public function __construct(
        private readonly NavigationTreeService $navigation,
        private readonly WhatsAppGatewayService $whatsAppGatewayService,
        private readonly BotSettingsService $settingsService,
    ) {
    }

    public function webhook(Request $request): JsonResponse
    {
        if (! $this->isValidWebhookSecret($request)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $payload = $request->all();
        $settings = $this->settingsService->get();
        $message = $this->extractIncomingMessage($payload, $settings['respond_to_groups']);

        if ($message === null) {
            return response()->json(['status' => 'ignored']);
        }

        if ($this->wasAlreadyProcessed($message)) {
            Log::info('Webhook duplicado ignorado.', ['instance_id' => $message['instance_id'], 'message_id' => $message['message_id']]);

            return response()->json(['status' => 'duplicate']);
        }

        try {
            if ($settings['response_mode'] === 'default') {
                $answer = $settings['default_message'];
            } else {
                $selection = $this->navigation->resolveNumber(
                    $message['text'],
                    Cache::get($this->navigationKey($message['phone']), []),
                );
                $response = $this->navigation->navigate($selection);
                if ($response['kind'] === 'answer') {
                    $this->whatsAppGatewayService->sendText($message['phone'], $response['text']);
                    $this->dispatchClaimAlert($response['id'], $message);
                    $menu = $this->navigation->menu($response['parent']);
                    $this->sendNavigationMenu($message['phone'], $this->navigation->followUp(), $menu);

                    return response()->json(['status' => 'sent']);
                }

                if ($selection === $message['text'] && ! str_starts_with($message['text'], 'nav:')) {
                    $this->whatsAppGatewayService->sendText($message['phone'], $this->navigation->welcome());
                }
                $this->sendNavigationMenu($message['phone'], $response['title'], $response);

                return response()->json(['status' => 'sent']);
            }

            $this->whatsAppGatewayService->sendText($message['phone'], $answer);

            return response()->json(['status' => 'sent']);
        } catch (Throwable $exception) {
            Log::error('Error procesando webhook de WhatsApp Web.', [
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            return response()->json(['message' => 'Unable to process message'], 500);
        }
    }

    /** @param array{button:string,rows:array<int,array{id:string,title:string,description:string}>} $menu */
    private function sendNavigationMenu(string $phone, string $title, array $menu): void
    {
        $optionIds = array_column($menu['rows'], 'id');
        Cache::put($this->navigationKey($phone), $optionIds, now()->addHours(2));
        $strategy = (string) config('services.whatsapp_web.menu_strategy', 'text');

        if (! in_array($strategy, ['text', 'buttons'], true)) {
            Log::warning('Estrategia de menú de WhatsApp inválida; se usa texto.', [
                'configured_strategy' => $strategy,
            ]);
            $strategy = 'text';
        }

        Log::info('Enviando menú de navegación de WhatsApp.', [
            'strategy' => $strategy,
            'phone_hash' => sha1($phone),
            'rows' => count($menu['rows']),
        ]);

        if ($strategy === 'text' || count($menu['rows']) > 10 || Cache::get($this->interactiveMenuFailureKey(), false)) {
            $this->whatsAppGatewayService->sendText($phone, $this->navigation->textMenu($title, $menu['rows']));

            return;
        }

        try {
            $this->whatsAppGatewayService->sendMenu($phone, $title, $menu['button'], $menu['rows']);
        } catch (Throwable $exception) {
            Cache::put($this->interactiveMenuFailureKey(), true, now()->addMinutes(10));
            Log::warning('Se envía el menú textual porque falló el menú interactivo.', [
                'phone_hash' => sha1($phone),
                'rows' => count($menu['rows']),
                'message' => $exception->getMessage(),
            ]);
            $this->whatsAppGatewayService->sendText($phone, $this->navigation->textMenu($title, $menu['rows']));
        }
    }

    private function navigationKey(string $phone): string
    {
        return 'whatsapp-navigation:'.sha1($phone);
    }

    private function interactiveMenuFailureKey(): string
    {
        return 'whatsapp-interactive-menu-failed:'.sha1((string) config('services.whatsapp_web.instance'));
    }

    public function status(Request $request): JsonResponse|RedirectResponse
    {
        if (! $request->wantsJson()) {
            return redirect()->route('bot.settings.edit');
        }

        return response()->json($this->whatsAppGatewayService->status());
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{phone:string,customer_phone:string,text:string,is_group:bool,instance_id:string,message_id:?string}|null
     */
    private function extractIncomingMessage(array $payload, bool $respondToGroups): ?array
    {
        $fromMe = (bool) data_get($payload, 'data.key.fromMe', false);
        $remoteJid = (string) data_get($payload, 'data.key.remoteJid', '');
        $text = trim((string) (
            data_get($payload, 'data.message.conversation')
            ?? data_get($payload, 'data.message.extendedTextMessage.text')
            ?? data_get($payload, 'data.message.listResponseMessage.singleSelectReply.selectedRowId')
            ?? data_get($payload, 'data.message.buttonsResponseMessage.selectedButtonId')
            ?? data_get($payload, 'data.message.templateButtonReplyMessage.selectedId')
            ?? data_get($payload, 'message.text')
            ?? data_get($payload, 'text')
            ?? ''
        ));

        $isGroup = str_ends_with($remoteJid, '@g.us');

        if ($fromMe || $text === '' || $remoteJid === '' || ($isGroup && ! $respondToGroups)) {
            return null;
        }

        return [
            'phone' => $isGroup ? $remoteJid : (preg_replace('/\D+/', '', $remoteJid) ?? $remoteJid),
            'customer_phone' => $this->customerPhone($payload, $remoteJid, $isGroup),
            'text' => $text,
            'is_group' => $isGroup,
            'instance_id' => (string) (data_get($payload, 'instanceId') ?? data_get($payload, 'instance') ?? config('services.whatsapp_web.instance')),
            'message_id' => data_get($payload, 'data.key.id') ?? data_get($payload, 'key.id') ?? data_get($payload, 'messageId'),
        ];
    }

    /**
     * @param array{customer_phone:string,is_group:bool,instance_id:string,message_id:?string} $message
     */
    private function dispatchClaimAlert(string $selection, array $message): void
    {
        $settings = $this->settingsService->get();
        if ($message['is_group'] || ! $settings['notifications_enabled'] || $settings['notification_group_id'] === '') {
            return;
        }

        $selectionId = str_starts_with($selection, 'nav:') ? substr($selection, 4) : $selection;
        if (! in_array($selectionId, ['booking_claim', 'missing_voucher'], true)) {
            return;
        }

        try {
            // Claim alerts are part of the webhook response path so they must not
            // depend on a separately running queue worker to reach the group.
            SendWhatsAppClaimAlert::dispatchSync($selectionId, $message['customer_phone']);
        } catch (Throwable $exception) {
            Log::error('No se pudo enviar la alerta administrativa de WhatsApp.', [
                'selection' => $selectionId,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /** @param array<string,mixed> $payload */
    private function customerPhone(array $payload, string $remoteJid, bool $isGroup): string
    {
        $jid = $isGroup
            ? (string) (data_get($payload, 'data.key.participant') ?? data_get($payload, 'data.participant') ?? '')
            : $remoteJid;

        return preg_replace('/\D+/', '', explode('@', $jid)[0]) ?? '';
    }

    /** @param array{instance_id:string,message_id:?string} $message */
    private function wasAlreadyProcessed(array $message): bool
    {
        if (! is_string($message['message_id']) || $message['message_id'] === '') {
            return false;
        }

        $key = 'whatsapp-webhook:'.sha1($message['instance_id'].'|'.$message['message_id']);

        return ! Cache::add($key, true, now()->addDay());
    }

    private function isValidWebhookSecret(Request $request): bool
    {
        $secret = (string) config('services.whatsapp_web.webhook_secret');

        return $secret === '' || hash_equals($secret, (string) $request->header('X-Webhook-Secret'));
    }
}
