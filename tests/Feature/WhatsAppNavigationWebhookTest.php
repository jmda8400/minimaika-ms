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
        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/message/sendButtons/rocca')
            && count($request['buttons']) === 3
            && $request['buttons'][0] === [
                'type' => 'reply',
                'displayText' => 'Tarifas y menú',
                'id' => 'nav:rates',
            ]);
        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/message/sendText/rocca')
            && str_contains((string) $request['text'], 'Respondé con el número de la opción.'));
    }

    public function test_main_menu_is_sent_as_groups_of_at_most_three_reply_buttons(): void
    {
        Storage::fake('local');
        config()->set('services.whatsapp_web.base_url', 'http://gateway.test');
        config()->set('services.whatsapp_web.api_key', 'test-key');
        config()->set('services.whatsapp_web.instance', 'rocca');

        Http::fake(['*' => Http::response(['ok' => true])]);

        $this->postJson(route('whatsapp.webhook'), [
            'instanceId' => 'rocca',
            'data' => [
                'key' => ['fromMe' => false, 'remoteJid' => '5492944000000@s.whatsapp.net', 'id' => 'message-buttons'],
                'message' => ['conversation' => 'Hola'],
            ],
        ])->assertOk()->assertJson(['status' => 'sent']);

        Http::assertSentCount(5);

        $buttonRequests = collect(Http::recorded())
            ->map(fn (array $pair) => $pair[0])
            ->filter(fn ($request): bool => str_contains($request->url(), '/message/sendButtons/rocca'));

        $this->assertCount(4, $buttonRequests);
        $this->assertSame([3, 3, 3, 1], $buttonRequests->map(fn ($request): int => count($request['buttons']))->values()->all());
        $this->assertSame('nav:claims', $buttonRequests->last()['buttons'][0]['id']);
    }
}
