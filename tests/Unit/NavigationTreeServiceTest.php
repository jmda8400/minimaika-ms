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
}
