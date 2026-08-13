<?php

namespace Tests\Unit;

use App\Services\Bot\NavigationTreeService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NavigationTreeServiceTest extends TestCase
{
    public function test_unknown_message_opens_deterministic_main_menu(): void
    {
        $response = app(NavigationTreeService::class)->navigate('cualquier texto libre');

        $this->assertSame('menu', $response['kind']);
        $this->assertCount(10, $response['rows']);
        $this->assertSame('nav:rates', $response['rows'][0]['id']);
    }

    public function test_selection_opens_submenu_and_leaf_returns_approved_answer(): void
    {
        $service = app(NavigationTreeService::class);
        $submenu = $service->navigate('nav:services');
        $answer = $service->navigate('nav:camping');

        $this->assertSame('menu', $submenu['kind']);
        $this->assertSame('Servicios', $submenu['title']);
        $this->assertSame('answer', $answer['kind']);
        $this->assertSame('services', $answer['parent']);
        $this->assertSame(
            'En la zona del refugio Parques Nacionales no permite el acampe, para consultar las zonas de acampe comunicate con Parques Nacionales.',
            $answer['text'],
        );
    }

    public function test_answers_keep_the_approved_wording_without_summarizing_it(): void
    {
        $service = app(NavigationTreeService::class);

        $rooms = $service->navigate('nav:rooms');
        $bookingClaim = $service->navigate('nav:booking_claim');

        $this->assertStringContainsString('El refugio posee 10 habitaciones con ocho camas en cada una.', $rooms['text']);
        $this->assertStringContainsString('pondremos siempre nuestra mejor voluntad.', $rooms['text']);
        $this->assertStringContainsString('Te recordamos los términos de la política de cambio', $bookingClaim['text']);
        $this->assertStringContainsString('Este cambio quedará sujeto a disponibilidad.', $bookingClaim['text']);
        $this->assertStringContainsString('• Fecha y código de reserva', $bookingClaim['text']);
    }

    public function test_welcome_contains_operational_links(): void
    {
        $welcome = app(NavigationTreeService::class)->welcome();

        $this->assertStringContainsString('PODRÁS REALIZAR TUS RESERVAS DESDE EL 1 DE OCTUBRE', $welcome);
        $this->assertStringContainsString('#estado-del-sendero', $welcome);
        $this->assertStringContainsString('windguru', $welcome);
    }

    public function test_text_menu_and_numeric_selection_are_deterministic(): void
    {
        $service = app(NavigationTreeService::class);
        $menu = $service->menu('payments');
        $text = $service->textMenu($menu['title'], $menu['rows']);
        $ids = array_column($menu['rows'], 'id');

        $this->assertStringContainsString("1. Pago en dólares", $text);
        $this->assertStringContainsString('Respondé con el número', $text);
        $this->assertSame('nav:dollars', $service->resolveNumber('1', $ids));
        $this->assertSame('99', $service->resolveNumber('99', $ids));
    }

    public function test_numeric_selection_accepts_common_natural_formats(): void
    {
        $service = app(NavigationTreeService::class);
        $ids = ['nav:first', 'nav:second', 'nav:third'];

        $this->assertSame('nav:second', $service->resolveNumber('2.', $ids));
        $this->assertSame('nav:second', $service->resolveNumber('opción 2', $ids));
        $this->assertSame('nav:second', $service->resolveNumber('Opcion 2', $ids));
        $this->assertSame('nav:second', $service->resolveNumber('2 - Servicios', $ids));
        $this->assertSame('opción 9', $service->resolveNumber('opción 9', $ids));
        $this->assertSame('entre 1 y 2', $service->resolveNumber('entre 1 y 2', $ids));
        $this->assertSame('2 - servicio 3', $service->resolveNumber('2 - servicio 3', $ids));
    }

    public function test_saved_texts_categories_and_new_options_are_used(): void
    {
        Storage::fake('local');
        Storage::put('bot-settings.json', json_encode([
            'bot_texts' => [
                'welcome' => 'Bienvenida personalizada',
                'main_menu_title' => 'Elegí tu consulta',
                'menu_button' => 'Abrir',
                'select_prompt' => 'Escribí un número.',
                'follow_up' => '¿Algo más?',
                'back_title' => 'Volver',
                'back_description' => 'Ir al inicio',
                'option_description' => 'Consultar',
            ],
            'navigation' => [[
                'id' => 'custom',
                'title' => 'Nueva categoría',
                'options' => [[
                    'id' => 'custom_answer',
                    'title' => 'Nueva opción',
                    'answer' => 'Respuesta personalizada',
                ]],
            ]],
        ]));

        $service = app(NavigationTreeService::class);

        $this->assertSame('Bienvenida personalizada', $service->welcome());
        $this->assertSame('Elegí tu consulta', $service->menu('main')['title']);
        $this->assertSame('Nueva categoría', $service->menu('main')['rows'][0]['title']);
        $this->assertSame('Respuesta personalizada', $service->navigate('nav:custom_answer')['text']);
        $this->assertStringEndsWith('Escribí un número.', $service->textMenu('Menú', $service->menu('custom')['rows']));
    }
}
