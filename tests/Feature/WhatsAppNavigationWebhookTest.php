<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WhatsAppNavigationWebhookTest extends TestCase
{
    public function test_failed_interactive_menu_falls_back_to_numbered_text_and_returns_ok(): void
    {
        Storage::fake('local');
        config()->set('services.whatsapp_web.base_url', 'http://gateway.test');
        config()->set('services.whatsapp_web.api_key', 'test-key');
        config()->set('services.whatsapp_web.instance', 'rocca');

        Http::fakeSequence()
            ->push(['ok' => true], 200)
            ->push(['status' => 400, 'error' => 'Bad Request'], 400)
            ->push(['ok' => true], 200);

        $this->postJson(route('whatsapp.webhook'), [
            'instanceId' => 'rocca',
            'data' => [
                'key' => ['fromMe' => false, 'remoteJid' => '5492944000000@s.whatsapp.net', 'id' => 'message-1'],
                'message' => ['conversation' => 'Hola'],
            ],
        ])->assertOk()->assertJson(['status' => 'sent']);

        Http::assertSentCount(3);
        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/message/sendText/rocca')
            && str_contains((string) $request['text'], 'Respondé con el número de la opción.'));
    }
}
