<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use App\Services\Bot\BotSettingsService;
use App\Services\Rag\RagBotService;
use App\Services\WhatsApp\WhatsAppGatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

class WhatsAppWebhookController extends Controller
{
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
            $answer = $settings['response_mode'] === 'default'
                ? $settings['default_message']
                : $this->ragBotService->answer($message['text'])['answer'];

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

    public function status(): JsonResponse
    {
        return response()->json($this->whatsAppGatewayService->status());
    }

    public function qr(Request $request): JsonResponse|RedirectResponse|Response
    {
        if (! $request->wantsJson() && $this->isConnected()) {
            return redirect()->route('bot.settings.edit')->with('status', 'WhatsApp conectado correctamente.');
        }

        $payload = $this->whatsAppGatewayService->qr();

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        $qrImage = (string) (data_get($payload, 'base64') ?? data_get($payload, 'qrcode.base64') ?? '');
        $pairingCode = (string) (data_get($payload, 'code') ?? data_get($payload, 'pairingCode') ?? '');

        return response($this->renderQrPage($qrImage, $pairingCode, route('whatsapp.webhook')));
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

    private function isConnected(): bool
    {
        try {
            $payload = $this->whatsAppGatewayService->status();
            $state = strtolower((string) (data_get($payload, 'instance.state') ?? data_get($payload, 'state') ?? data_get($payload, 'status') ?? ''));

            return in_array($state, ['open', 'connected', 'online'], true);
        } catch (Throwable) {
            return false;
        }
    }

    private function renderQrPage(string $qrImage, string $pairingCode, string $webhookUrl): string
    {
        $qrMarkup = $qrImage !== ''
            ? '<img class="qr" src="'.e($qrImage).'" alt="Código QR de WhatsApp">'
            : '<p class="muted">El gateway todavía no devolvió una imagen QR. Esta página se actualiza automáticamente.</p>';
        $codeMarkup = $pairingCode !== ''
            ? '<p class="muted">Código:</p><p class="code">'.e($pairingCode).'</p>'
            : '';

        $safeWebhookUrl = e($webhookUrl);
        $settingsUrl = e(route('bot.settings.edit'));
        $qrUrl = e(route('whatsapp.qr'));
        $statusUrl = e(route('whatsapp.status'));

        return <<<HTML
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="20">
    <title>WhatsApp QR - Refugio Agostino Rocca</title>
    <style>body{font-family:system-ui,-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif;min-height:100vh;margin:0;background:#f7f7f3;color:#1d1d1b}.site-header{background:#fff;border-bottom:1px solid #ece7d9}.site-header__inner{max-width:860px;margin:0 auto;padding:16px 20px;display:flex;justify-content:space-between;gap:16px;align-items:center}.brand{color:#1d1d1b;font-weight:850;text-decoration:none}.nav{display:flex;gap:8px;flex-wrap:wrap}.nav a{border:1px solid #ded7c5;border-radius:999px;color:#2f5d50;padding:8px 12px;text-decoration:none;font-weight:750}.nav a.active{background:#2f5d50;color:#fff;border-color:#2f5d50}.page{display:grid;place-items:center;padding:40px 20px}.card{background:white;border-radius:18px;box-shadow:0 12px 40px #0002;max-width:520px;padding:32px;text-align:center}.qr{max-width:320px;width:100%;height:auto}.muted{color:#666;line-height:1.5}.code{font:700 28px ui-monospace,Menlo,monospace;letter-spacing:3px}</style>
</head>
<body>
    <header class="site-header">
        <div class="site-header__inner">
            <a class="brand" href="{$settingsUrl}">WhatsApp Admin</a>
            <nav class="nav" aria-label="Navegación WhatsApp">
                <a href="{$settingsUrl}">Settings</a>
                <a class="active" href="{$qrUrl}">QR</a>
                <a href="{$statusUrl}">Status JSON</a>
            </nav>
        </div>
    </header>
    <main class="page">
    <section class="card">
        <h1>Conectar WhatsApp</h1>
        <p class="muted">Abrí WhatsApp en el celular, entrá a Dispositivos vinculados y escaneá este código.</p>
        {$qrMarkup}
        {$codeMarkup}
        <p class="muted">URL del webhook: <strong>{$safeWebhookUrl}</strong></p>
    </section>
    </main>
</body>
</html>
HTML;
    }

    private function isValidWebhookSecret(Request $request): bool
    {
        $secret = (string) config('services.whatsapp_web.webhook_secret');

        return $secret === '' || hash_equals($secret, (string) $request->header('X-Webhook-Secret'));
    }
}
