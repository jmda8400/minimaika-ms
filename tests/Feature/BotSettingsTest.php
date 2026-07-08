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

        $this->put(route('bot.settings.update'), [
            'response_mode' => 'default',
            'default_message' => 'Te respondemos pronto.',
            'respond_to_groups' => '1',
        ])->assertRedirect(route('bot.settings.edit'));

        Storage::disk('local')->assertExists('bot-settings.json');
        $settings = json_decode(Storage::disk('local')->get('bot-settings.json'), true);

        $this->assertSame('default', $settings['response_mode']);
        $this->assertSame('Te respondemos pronto.', $settings['default_message']);
        $this->assertTrue($settings['respond_to_groups']);
    }


    public function test_settings_page_lives_under_whatsapp_and_shows_status(): void
    {
        Storage::fake('local');

        $this->mock(WhatsAppGatewayService::class, function ($mock): void {
            $mock->shouldReceive('status')->once()->andReturn(['instance' => ['state' => 'open']]);
            $mock->shouldNotReceive('qr');
        });

        $this->get('/whatsapp/settings')
            ->assertOk()
            ->assertDontSee('WhatsApp Admin')
            ->assertSee('Conectado')
            ->assertSee('Respuesta de /whatsapp/status')
            ->assertSee('Olvidar sesión y pedir QR nuevo');
    }

    public function test_qr_page_redirects_to_settings_when_whatsapp_is_connected(): void
    {
        $this->mock(WhatsAppGatewayService::class, function ($mock): void {
            $mock->shouldReceive('status')->once()->andReturn(['instance' => ['state' => 'open']]);
            $mock->shouldNotReceive('qr');
        });

        $this->get('/whatsapp/qr')
            ->assertRedirect(route('bot.settings.edit'));
    }

    public function test_qr_page_has_no_navigation_header_when_whatsapp_is_not_connected(): void
    {
        $this->mock(WhatsAppGatewayService::class, function ($mock): void {
            $mock->shouldReceive('status')->once()->andReturn(['instance' => ['state' => 'close']]);
            $mock->shouldReceive('qr')->once()->andReturn(['base64' => 'data:image/png;base64,abc']);
        });

        $this->get('/whatsapp/qr')
            ->assertOk()
            ->assertDontSee('WhatsApp Admin')
            ->assertDontSee('/whatsapp/status');
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

        $this->get('/whatsapp/settings')
            ->assertOk()
            ->assertSee('Conectar con QR')
            ->assertSee('data:image/png;base64,abc');
    }

    public function test_forget_session_logs_out_whatsapp(): void
    {
        $this->mock(WhatsAppGatewayService::class, function ($mock): void {
            $mock->shouldReceive('logout')->once()->andReturn(['status' => 'success']);
        });

        $this->post(route('bot.settings.forget-session'))
            ->assertRedirect(route('bot.settings.edit'))
            ->assertSessionHas('status', 'Sesión de WhatsApp olvidada. Escaneá un QR nuevo para volver a conectar.');
    }

    public function test_webhook_uses_default_message_when_configured(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('bot-settings.json', json_encode([
            'response_mode' => 'default',
            'default_message' => 'Mensaje fijo.',
            'respond_to_groups' => false,
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
}
