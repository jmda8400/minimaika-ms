<?php

namespace Tests\Feature;

use App\Services\WhatsApp\WhatsAppGatewayService;
use App\Services\WhatsApp\WhatsAppNotificationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WhatsAppNotificationsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config()->set('services.whatsapp_web.base_url', 'http://gateway.test');
        config()->set('services.whatsapp_web.api_key', 'test-key');
        config()->set('services.whatsapp_web.instance', 'rocca');
    }

    public function test_gateway_lists_and_normalizes_whatsapp_groups(): void
    {
        Http::fake(['*' => Http::response([
            ['id' => '1202@g.us', 'subject' => 'Operaciones'],
            ['id' => 'person@s.whatsapp.net', 'name' => 'Persona'],
            ['groupMetadata' => ['id' => '1201@g.us', 'subject' => 'Administración']],
        ])]);

        $this->assertSame([
            ['id' => '1201@g.us', 'name' => 'Administración'],
            ['id' => '1202@g.us', 'name' => 'Operaciones'],
        ], app(WhatsAppGatewayService::class)->groups());

        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/group/fetchAllGroups/rocca')
            && $request['getParticipants'] === 'false');
    }

    public function test_voucher_selection_immediately_sends_an_alert_with_the_customer_phone(): void
    {
        $this->storeNotificationSettings();
        Http::fake(['*' => Http::response(['ok' => true])]);

        $payload = [
            'instanceId' => 'rocca',
            'data' => [
                'key' => ['fromMe' => false, 'remoteJid' => '5492944000000@s.whatsapp.net', 'id' => 'claim-1'],
                'message' => ['conversation' => 'nav:missing_voucher'],
            ],
        ];

        $this->postJson(route('whatsapp.webhook'), $payload)->assertOk()->assertJson(['status' => 'sent']);
        $this->postJson(route('whatsapp.webhook'), $payload)->assertOk()->assertJson(['status' => 'duplicate']);

        Http::assertSentCount(3);
        Http::assertSent(fn ($request): bool => $request['number'] === '120363000000000000@g.us'
            && str_contains($request['text'], '+5492944000000')
            && str_contains($request['text'], 'no haber recibido el voucher'));
    }

    public function test_claim_selected_by_its_visible_button_text_sends_the_alert(): void
    {
        $this->storeNotificationSettings();
        Http::fake(['*' => Http::response(['ok' => true])]);

        $this->postJson(route('whatsapp.webhook'), [
            'instanceId' => 'rocca',
            'data' => [
                'key' => ['fromMe' => false, 'remoteJid' => '5492944000000@s.whatsapp.net', 'id' => 'claim-title'],
                'message' => ['extendedTextMessage' => ['text' => 'Por reservas']],
            ],
        ])->assertOk()->assertJson(['status' => 'sent']);

        Http::assertSent(fn ($request): bool => $request['number'] === '120363000000000000@g.us'
            && str_contains($request['text'], '+5492944000000')
            && str_contains($request['text'], 'problemas con la reserva'));
    }

    public function test_claim_alert_is_sent_to_the_configured_group(): void
    {
        $this->storeNotificationSettings();
        Http::fake(['*' => Http::response(['ok' => true])]);

        $sent = app(WhatsAppNotificationService::class)->sendClaim('booking_claim', '5492944000000');

        $this->assertTrue($sent);
        Http::assertSent(fn ($request): bool => $request['number'] === '120363000000000000@g.us'
            && str_contains($request['text'], '+5492944000000')
            && str_contains($request['text'], 'indica tener problemas con la reserva'));
    }

    public function test_forced_heartbeat_only_reports_a_connected_session(): void
    {
        $this->storeNotificationSettings();
        Cache::flush();
        Http::fakeSequence()
            ->push(['instance' => ['state' => 'open']])
            ->push(['ok' => true]);

        $this->assertTrue(app(WhatsAppNotificationService::class)->sendHeartbeat(true));

        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/message/sendText/rocca')
            && $request['number'] === '120363000000000000@g.us'
            && str_contains($request['text'], 'Bot operativo')
            && str_contains($request['text'], 'se encuentra conectada'));
    }

    public function test_disconnected_session_does_not_send_a_false_heartbeat(): void
    {
        $this->storeNotificationSettings();
        Cache::flush();
        Http::fake(['*' => Http::response(['instance' => ['state' => 'close']])]);

        $this->assertFalse(app(WhatsAppNotificationService::class)->sendHeartbeat(true));
        Http::assertSentCount(1);
        $this->assertSame('La sesión no está conectada (estado: close).', Cache::get('whatsapp-heartbeat-last-error')['message']);
    }

    private function storeNotificationSettings(): void
    {
        Storage::put('bot-settings.json', json_encode([
            'notifications_enabled' => true,
            'notification_group_id' => '120363000000000000@g.us',
            'notification_group_name' => 'Administración',
            'heartbeat_enabled' => true,
            'heartbeat_time' => '09:00',
            'heartbeat_timezone' => 'America/Argentina/Buenos_Aires',
        ]));
    }
}
