<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use App\Services\Bot\BotSettingsService;
use App\Services\Rag\RagBotService;
use App\Services\WhatsApp\WhatsAppGatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class WhatsAppWebhookController extends Controller
{
    private const KNOWLEDGE_FALLBACK_ALERT_PHONE = '2944360712';

    public function __construct(
        private readonly RagBotService $ragBotService,
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

        try {
            if ($settings['response_mode'] === 'default') {
                $answer = $settings['default_message'];
            } else {
                $botResponse = $this->ragBotService->answer($message['text'], null, $settings['use_generative_ai']);
                $answer = $botResponse['answer'];

                if (($botResponse['source_type'] ?? null) === 'fallback') {
                    $this->sendKnowledgeFallbackAlert($message['phone'], $message['text']);
                }
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

    private function sendKnowledgeFallbackAlert(string $userPhone, string $question): void
    {
        $alert = "Aviso bot refugio: no encontré información confirmada en la base de conocimiento para responder esta consulta.\n"
            ."Usuario: {$userPhone}\n"
            ."Consulta: {$question}";

        $this->whatsAppGatewayService->sendText(self::KNOWLEDGE_FALLBACK_ALERT_PHONE, $alert);
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
     * @return array{phone:string,text:string,is_group:bool}|null
     */
    private function extractIncomingMessage(array $payload, bool $respondToGroups): ?array
    {
        $fromMe = (bool) data_get($payload, 'data.key.fromMe', false);
        $remoteJid = (string) data_get($payload, 'data.key.remoteJid', '');
        $text = trim((string) (
            data_get($payload, 'data.message.conversation')
            ?? data_get($payload, 'data.message.extendedTextMessage.text')
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
            'text' => $text,
            'is_group' => $isGroup,
        ];
    }

    private function isValidWebhookSecret(Request $request): bool
    {
        $secret = (string) config('services.whatsapp_web.webhook_secret');

        return $secret === '' || hash_equals($secret, (string) $request->header('X-Webhook-Secret'));
    }
}
