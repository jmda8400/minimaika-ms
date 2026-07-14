<?php

namespace Tests\Unit;

use App\Services\Bot\PrewrittenResponseService;
use PHPUnit\Framework\TestCase;

class PrewrittenResponseServiceTest extends TestCase
{
    public function test_detects_tac_as_gluten_free_food(): void
    {
        $service = new PrewrittenResponseService();

        $this->assertSame('comida_sin_gluten', $service->detect('Tienen comida sin TAC?'));
        $this->assertStringContainsString('contaminación cruzada', $service->get('comida_sin_gluten'));
    }

    public function test_detects_road_schedule(): void
    {
        $service = new PrewrittenResponseService();

        $this->assertSame('horario_camino_pampa_linda', $service->detect('Cuál es el horario del camino a Pampa Linda?'));
    }
}
