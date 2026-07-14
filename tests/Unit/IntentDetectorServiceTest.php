<?php

namespace Tests\Unit;

use App\Services\Bot\IntentDetectorService;
use PHPUnit\Framework\TestCase;

class IntentDetectorServiceTest extends TestCase
{
    public function test_gluten_question_has_specific_human_answer_without_tariff_prefix(): void
    {
        $intent = $this->detect('Hola, tienen comida sin gluten?');

        $this->assertSame('comida_sin_gluten', $intent['intent']);
        $this->assertFalse($intent['use_rag']);
        $this->assertStringContainsString('Tenemos opciones sin gluten', $intent['response']);
        $this->assertStringContainsString('contaminación cruzada', $intent['response']);
        $this->assertStringNotContainsString('No tengo permitido inventar precios', $intent['response']);
        $this->assertNull($intent['prepend_response']);
    }


    public function test_tac_question_has_specific_human_answer(): void
    {
        $intent = $this->detect('Tienen comida sin TAC?');

        $this->assertSame('comida_sin_gluten', $intent['intent']);
        $this->assertFalse($intent['use_rag']);
        $this->assertStringContainsString('contaminación cruzada', $intent['response']);
    }

    public function test_tacc_question_has_specific_human_answer(): void
    {
        $intent = $this->detect('Tienen menú sin TACC?');

        $this->assertSame('comida_sin_gluten', $intent['intent']);
        $this->assertFalse($intent['use_rag']);
    }

    public function test_vegan_question_uses_rag_without_tariff_prefix(): void
    {
        $intent = $this->detect('Buenas, tienen comida vegana?');

        $this->assertSame('restricciones_alimentarias', $intent['intent']);
        $this->assertTrue($intent['use_rag']);
        $this->assertNull($intent['response']);
        $this->assertNull($intent['prepend_response']);
    }

    public function test_food_question_without_price_terms_does_not_trigger_tariffs(): void
    {
        $intent = (new IntentDetectorService())->detect('Se puede pedir comida al llegar?');

        $this->assertNotSame('precio_tarifas', $intent['intent'] ?? null);
    }

    public function test_explicit_food_price_question_triggers_tariffs(): void
    {
        $intent = $this->detect('Cuánto cuesta la comida?');

        $this->assertSame('precio_tarifas', $intent['intent']);
        $this->assertSame('Los valores de pernocte, comidas y servicios deben consultarse en la sección de tarifas de la web oficial del refugio. No tengo permitido inventar precios. También puedo contarte qué servicios tienen costo aparte, como la ducha.', $intent['prepend_response']);
    }

    public function test_greeting_inside_a_real_question_does_not_hide_the_question(): void
    {
        $intent = $this->detect('Hola, quiero reservar');

        $this->assertSame('reserva', $intent['intent']);
    }

    /** @return array{intent:string,response:?string,use_rag:bool,show_auto_source:bool,prepend_response:?string} */
    private function detect(string $message): array
    {
        $intent = (new IntentDetectorService())->detect($message);

        $this->assertNotNull($intent);

        return $intent;
    }
}
