<?php

namespace Tests\Feature;

use App\Services\Rag\RagBotService;
use App\Services\WhatsApp\WhatsAppGatewayService;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class BotSettingsTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_settings_page_can_be_updated(): void
    {
        Storage::fake('local');

        $this->authenticatedRequest()->put(route('bot.settings.update'), [
            'response_mode' => 'default',
            'default_message' => 'Te respondemos pronto.',
            'respond_to_groups' => '1',
            'notify_on_fallback' => '1',
            'fallback_alert_phone' => '+54 2944360712',
        ])->assertRedirect(route('bot.settings.edit'));

        Storage::disk('local')->assertExists('bot-settings.json');
        $settings = json_decode(Storage::disk('local')->get('bot-settings.json'), true);

        $this->assertSame('default', $settings['response_mode']);
        $this->assertSame('Te respondemos pronto.', $settings['default_message']);
        $this->assertTrue($settings['respond_to_groups']);
        $this->assertTrue($settings['use_generative_ai']);
        $this->assertTrue($settings['notify_on_fallback']);
        $this->assertSame('+54 2944360712', $settings['fallback_alert_phone']);
    }


    public function test_settings_page_lives_under_whatsapp_and_shows_status(): void
    {
        Storage::fake('local');

        $this->mock(WhatsAppGatewayService::class, function ($mock): void {
            $mock->shouldReceive('status')->once()->andReturn(['instance' => ['state' => 'open']]);
            $mock->shouldNotReceive('qr');
        });

        $this->authenticatedRequest()->get('/whatsapp/settings')
            ->assertOk()
            ->assertDontSee('WhatsApp Admin')
            ->assertSee('Conectado')
            ->assertSee('Respuesta de /whatsapp/status')
            ->assertSee('Olvidar sesión y pedir QR nuevo')
            ->assertSee('Volver a Administracion')
            ->assertSee('Reenviar mensajes que el bot no pudo interpretar')
            ->assertSee('+54 2944360712')
            ->assertDontSee('Responder con IA generativa (Groq)')
            ->assertDontSee('Groq redacta la respuesta usando la base de conocimiento')
            ->assertSee('Respuestas: predefinidas, con encauzamiento automático cuando haga falta');
    }

    public function test_qr_page_redirects_to_settings_because_settings_shows_qr(): void
    {
        $this->get('/whatsapp/qr')
            ->assertRedirect('/whatsapp/settings');
    }


    public function test_status_page_redirects_to_settings_for_browser_requests(): void
    {
        $this->get('/whatsapp/status')
            ->assertRedirect(route('bot.settings.edit'));
    }

    public function test_settings_page_shows_qr_when_whatsapp_is_not_connected(): void
    {
        Storage::fake('local');

        $this->mock(WhatsAppGatewayService::class, function ($mock): void {
            $mock->shouldReceive('status')->once()->andReturn(['instance' => ['state' => 'close']]);
            $mock->shouldReceive('qr')->once()->andReturn(['base64' => 'data:image/png;base64,abc']);
        });

        $this->authenticatedRequest()->get('/whatsapp/settings')
            ->assertOk()
            ->assertSee('Conectar con QR')
            ->assertSee('data:image/png;base64,abc');
    }

    public function test_forget_session_logs_out_whatsapp(): void
    {
        $this->mock(WhatsAppGatewayService::class, function ($mock): void {
            $mock->shouldReceive('logout')->once()->andReturn(['status' => 'success']);
        });

        $this->authenticatedRequest()->post(route('bot.settings.forget-session'))
            ->assertRedirect(route('bot.settings.edit'))
            ->assertSessionHas('status', 'Sesión de WhatsApp olvidada. QR nuevo disponible para reconexión.');
    }

    public function test_webhook_uses_default_message_when_configured(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('bot-settings.json', json_encode([
            'response_mode' => 'default',
            'default_message' => 'Mensaje fijo.',
            'respond_to_groups' => false,
            'use_generative_ai' => false,
            'notify_on_fallback' => true,
            'fallback_alert_phone' => '+54 2944360712',
        ]));

        $this->mock(RagBotService::class, function ($mock): void {
            $mock->shouldNotReceive('answer');
        });

        $this->mock(WhatsAppGatewayService::class, function ($mock): void {
            $mock->shouldReceive('sendText')->once()->with('5492944000000', 'Mensaje fijo.');
        });

        $this->postJson(route('whatsapp.webhook'), [
            'data' => [
                'key' => ['fromMe' => false, 'remoteJid' => '5492944000000@s.whatsapp.net'],
                'message' => ['conversation' => 'Hola'],
            ],
        ])->assertOk()->assertJson(['status' => 'sent']);
    }

    public function test_webhook_passes_generative_ai_setting_to_bot(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('bot-settings.json', json_encode([
            'response_mode' => 'bot',
            'default_message' => 'Mensaje fijo.',
            'respond_to_groups' => false,
            'use_generative_ai' => false,
            'notify_on_fallback' => true,
            'fallback_alert_phone' => '+54 2944360712',
        ]));

        $this->mock(RagBotService::class, function ($mock): void {
            $mock->shouldReceive('answer')->once()->with('Hola', null, false, '5492944000000')->andReturn(['answer' => 'Respuesta sin IA generativa.']);
        });

        $this->mock(WhatsAppGatewayService::class, function ($mock): void {
            $mock->shouldReceive('sendText')->once()->with('5492944000000', 'Respuesta sin IA generativa.');
        });

        $this->postJson(route('whatsapp.webhook'), [
            'data' => [
                'key' => ['fromMe' => false, 'remoteJid' => '5492944000000@s.whatsapp.net'],
                'message' => ['conversation' => 'Hola'],
            ],
        ])->assertOk()->assertJson(['status' => 'sent']);
    }

    public function test_webhook_notifies_configured_phone_when_the_bot_falls_back(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('bot-settings.json', json_encode([
            'response_mode' => 'bot',
            'default_message' => 'Mensaje fijo.',
            'respond_to_groups' => false,
            'use_generative_ai' => false,
            'notify_on_fallback' => true,
            'fallback_alert_phone' => '+54 2944360712',
        ]));

        $this->mock(RagBotService::class, function ($mock): void {
            $mock->shouldReceive('answer')->once()->with('Consulta inexistente', null, false, '5492944000000')->andReturn([
                'answer' => 'No tengo esa información confirmada.',
                'source_type' => 'fallback',
            ]);
        });

        $this->mock(WhatsAppGatewayService::class, function ($mock): void {
            $mock->shouldReceive('sendText')->once()->with('5492944000000', 'No tengo esa información confirmada.');
            $mock->shouldReceive('sendText')->once()->with('+54 2944360712', "No he podido descifrar la intencion del siguiente mensaje:\nConsulta inexistente");
        });

        $this->postJson(route('whatsapp.webhook'), [
            'data' => [
                'key' => ['fromMe' => false, 'remoteJid' => '5492944000000@s.whatsapp.net'],
                'message' => ['conversation' => 'Consulta inexistente'],
            ],
        ])->assertOk()->assertJson(['status' => 'sent']);
    }

    public function test_webhook_ignores_a_duplicate_message_id_without_calling_the_bot_or_gateway(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('bot-settings.json', json_encode(['response_mode' => 'bot', 'respond_to_groups' => false, 'use_generative_ai' => false]));
        $payload = ['instanceId' => 'refugio', 'data' => ['key' => ['id' => 'ABC-123', 'fromMe' => false, 'remoteJid' => '5492944000000@s.whatsapp.net'], 'message' => ['conversation' => 'Hola']]];

        $this->mock(RagBotService::class, function ($mock): void {
            $mock->shouldReceive('answer')->once()->andReturn(['answer' => 'Hola']);
        });
        $this->mock(WhatsAppGatewayService::class, function ($mock): void {
            $mock->shouldReceive('sendText')->once()->with('5492944000000', 'Hola');
        });

        $this->postJson(route('whatsapp.webhook'), $payload)->assertOk()->assertJson(['status' => 'sent']);
        $this->postJson(route('whatsapp.webhook'), $payload)->assertOk()->assertJson(['status' => 'duplicate']);
    }

    public function test_webhook_ignores_groups_unless_enabled(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('bot-settings.json', json_encode([
            'response_mode' => 'default',
            'default_message' => 'Mensaje fijo.',
            'respond_to_groups' => false,
        ]));

        $this->mock(WhatsAppGatewayService::class, function ($mock): void {
            $mock->shouldNotReceive('sendText');
        });

        $this->postJson(route('whatsapp.webhook'), [
            'data' => [
                'key' => ['fromMe' => false, 'remoteJid' => '120363000000000000@g.us'],
                'message' => ['conversation' => 'Hola grupo'],
            ],
        ])->assertOk()->assertJson(['status' => 'ignored']);
    }

    public function test_webhook_does_not_alert_when_fallback_notifications_are_disabled(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('bot-settings.json', json_encode([
            'response_mode' => 'bot',
            'default_message' => 'Mensaje fijo.',
            'respond_to_groups' => false,
            'use_generative_ai' => false,
            'notify_on_fallback' => false,
            'fallback_alert_phone' => '+54 2944360712',
        ]));

        $this->mock(RagBotService::class, function ($mock): void {
            $mock->shouldReceive('answer')->once()->andReturn([
                'answer' => 'No tengo esa información confirmada.',
                'source_type' => 'fallback',
            ]);
        });

        $this->mock(WhatsAppGatewayService::class, function ($mock): void {
            $mock->shouldReceive('sendText')->once()->with('5492944000000', 'No tengo esa información confirmada.');
        });

        $this->postJson(route('whatsapp.webhook'), [
            'data' => [
                'key' => ['fromMe' => false, 'remoteJid' => '5492944000000@s.whatsapp.net'],
                'message' => ['conversation' => 'Consulta inexistente'],
            ],
        ])->assertOk()->assertJson(['status' => 'sent']);
    }

    public function test_settings_require_login(): void
    {
        $this->get(route('bot.settings.edit'))
            ->assertRedirect(route('bot.settings.login'));
    }

    public function test_home_page_requires_login(): void
    {
        $this->get('/')
            ->assertRedirect(route('bot.settings.login'));

        $this->authenticatedRequest()->get('/')
            ->assertOk();
    }

    public function test_valid_credentials_grant_access_to_settings(): void
    {
        Storage::fake('local');

        $this->mock(WhatsAppGatewayService::class, function ($mock): void {
            $mock->shouldReceive('status')->once()->andReturn(['instance' => ['state' => 'open']]);
            $mock->shouldNotReceive('qr');
        });

        $this->post(route('bot.settings.login.store'), [
            'username' => 'chatbot',
            'password' => 'frias',
        ])->assertRedirect(route('bot.settings.edit'));

        $this->get(route('bot.settings.edit'))->assertOk();
    }

    public function test_invalid_credentials_do_not_grant_access_to_settings(): void
    {
        $this->from(route('bot.settings.login'))
            ->post(route('bot.settings.login.store'), [
                'username' => 'chatbot',
                'password' => 'incorrecta',
            ])
            ->assertRedirect(route('bot.settings.login'))
            ->assertSessionHasErrors('username');

        $this->get(route('bot.settings.edit'))
            ->assertRedirect(route('bot.settings.login'));
    }

    private function authenticatedRequest(): static
    {
        return $this->withSession(['bot-settings.authenticated' => true]);
    }
}
