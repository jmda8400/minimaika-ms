<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use App\Services\Rag\RagBotService;
use App\Services\WhatsApp\WhatsAppGatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

class WhatsAppWebhookController extends Controller
{
    public function __construct(
        private readonly RagBotService $ragBotService,
        private readonly WhatsAppGatewayService $whatsAppGatewayService,
    ) {
    }

    public function webhook(Request $request): JsonResponse
    {
        if (! $this->isValidWebhookSecret($request)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $payload = $request->all();
        $message = $this->extractIncomingMessage($payload);

        if ($message === null) {
            return response()->json(['status' => 'ignored']);
        }

        try {
            $answer = $this->ragBotService->answer($message['text'])['answer'];
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

    public function qr(Request $request): JsonResponse|Response
    {
        $payload = $this->whatsAppGatewayService->qr();

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        $qrImage = (string) (data_get($payload, 'base64') ?? data_get($payload, 'qrcode.base64') ?? '');
        $pairingCode = (string) (data_get($payload, 'code') ?? data_get($payload, 'pairingCode') ?? '');

        return response($this->renderQrPage($qrImage, $pairingCode));
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{phone:string,text:string}|null
     */
    private function extractIncomingMessage(array $payload): ?array
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

        if ($fromMe || $text === '' || $remoteJid === '' || str_ends_with($remoteJid, '@g.us')) {
            return null;
        }

        return [
            'phone' => preg_replace('/\D+/', '', $remoteJid) ?? $remoteJid,
            'text' => $text,
        ];
    }

    private function renderQrPage(string $qrImage, string $pairingCode): string
    {
        $qrMarkup = $qrImage !== ''
            ? '<img class="qr" src="'.e($qrImage).'" alt="Código QR de WhatsApp">'
            : '<p class="muted">El gateway todavía no devolvió una imagen QR. Esta página se actualiza automáticamente.</p>';
        $codeMarkup = $pairingCode !== ''
            ? '<p class="muted">Código:</p><p class="code">'.e($pairingCode).'</p>'
            : '';

        return <<<HTML
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="20">
    <title>WhatsApp QR - Refugio Agostino Rocca</title>
    <style>body{font-family:system-ui,-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif;display:grid;min-height:100vh;place-items:center;margin:0;background:#f7f7f3;color:#1d1d1b}.card{background:white;border-radius:18px;box-shadow:0 12px 40px #0002;max-width:520px;padding:32px;text-align:center}.qr{max-width:320px;width:100%;height:auto}.muted{color:#666;line-height:1.5}.code{font:700 28px ui-monospace,Menlo,monospace;letter-spacing:3px}</style>
</head>
<body>
    <main class="card">
        <h1>Conectar WhatsApp</h1>
        <p class="muted">Abrí WhatsApp en el celular, entrá a Dispositivos vinculados y escaneá este código.</p>
        {$qrMarkup}
        {$codeMarkup}
        <p class="muted">URL del webhook: <strong>https://bot.refugioagostinorocca.com/whatsapp/webhook</strong></p>
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
