<?php

namespace Tests\Unit;

use App\Services\Bot\NavigationTreeService;
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
        $this->assertStringContainsString('no permite acampar', $answer['text']);
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
}
