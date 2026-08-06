<?php

namespace Tests\Unit;

use App\Services\Bot\ApprovedResponseCatalog;
use App\Services\Bot\IntentDetectorService;
use App\Services\Bot\PrewrittenResponseService;
use App\Services\GroqChatService;
use App\Services\Rag\KnowledgeBaseService;
use App\Services\Rag\RagBotService;
use Tests\TestCase;

class ControlledRagRouterTest extends TestCase
{
    public function test_every_catalog_record_preserves_the_required_search_schema(): void
    {
        $records = (new ApprovedResponseCatalog())->all();

        foreach ($records as $id => $record) {
            $this->assertArrayHasKey('aliases', $record, "Catalog record {$id} has no aliases field.");
            $this->assertIsArray($record['aliases'], "Catalog record {$id} aliases must be an array.");
        }

        $this->assertSame([], $records['fallback.unknown']['aliases']);
    }

    /** @dataProvider approvedQueries */
    public function test_required_queries_return_only_approved_catalog_entries(string $query, string $expectedTopic): void
    {
        $result = $this->router()->answer($query, null, 'disabled', '5492944000000');

        $this->assertSame($expectedTopic, $result['sources'][0]['topic']);
        $this->assertNotSame('', $result['answer']);
    }

    public static function approvedQueries(): array
    {
        return [
            'ambiguous water' => ['Hay agua?', 'clarification'],
            'trail water' => ['Hay agua en el sendero?', 'trail_water'],
            'hot water' => ['Hay agua caliente?', 'hot_water'],
            'mate water' => ['Hay agua para el mate?', 'hot_water'],
            'meal reservation' => ['Tengo que reservar comida?', 'meal_reservation'],
            'lodging reservation' => ['Tengo que reservar para dormir?', 'lodging_reservation'],
            'gluten free' => ['Hay comida para celíacos?', 'gluten_free'],
            'generic reservation' => ['¿Cómo hago para reservar?', 'lodging_reservation'],
            'November reservation' => ['¿Cómo hago para reservar en noviembre?', 'reserva_inicio_temporada'],
            'Pampa Linda road' => ['¿Cómo es el camino a Pampa Linda?', 'horario_camino_pampa_linda'],
            'road condition' => ['¿Qué tal el estado del camino?', 'estado_sendero'],
            'Rocca trail condition' => ['¿Qué tal el sendero al Rocca?', 'estado_sendero'],
            'unknown' => ['teletransportame a una galaxia desconocida', 'fallback'],
        ];
    }

    public function test_water_clarification_is_kept_by_phone_and_resolved(): void
    {
        $router = $this->router();
        $router->answer('Hay agua?', null, 'disabled', 'phone-a');

        $resolved = $router->answer('en el sendero', null, 'disabled', 'phone-a');
        $otherPhone = $router->answer('en el sendero', null, 'disabled', 'phone-b');

        $this->assertSame('trail_water', $resolved['sources'][0]['topic']);
        $this->assertNotSame('clarification_resolved', $otherPhone['source_type']);
    }

    public function test_two_classified_intents_return_the_two_exact_approved_answers(): void
    {
        $groq = $this->createMock(GroqChatService::class);
        $groq->method('routeApprovedResponse')->willReturn([
            'intents' => [
                ['intent_id' => 'answer.formas_pago_refugio', 'confidence' => .95],
                ['intent_id' => 'answer.cargar_celular', 'confidence' => .91],
            ],
            'entities' => ['dates' => [], 'quantities' => [], 'codes' => []],
            '_raw_response' => '{}',
        ]);
        $router = new RagBotService(
            $this->createMock(KnowledgeBaseService::class),
            new IntentDetectorService(),
            $groq,
            new PrewrittenResponseService(),
        );

        $result = $router->answer('¿Puedo abonar con tarjeta y cargar mi teléfono?', null, 'semantic_classifier', 'phone-a');
        $catalog = new ApprovedResponseCatalog();

        $this->assertSame([
            'answer.formas_pago_refugio',
            'answer.cargar_celular',
        ], array_column($result['sources'], 'id'));
        $this->assertSame(
            $catalog->active('answer.formas_pago_refugio')['approved_answer']."\n\n".$catalog->active('answer.cargar_celular')['approved_answer'],
            $result['answer'],
        );
    }

    public function test_semantic_classifier_can_recognize_an_unlisted_greeting(): void
    {
        $groq = $this->createMock(GroqChatService::class);
        $groq->expects($this->once())->method('routeApprovedResponse')
            ->with('Muy buenas gente', $this->callback(static fn (array $candidates): bool => in_array('answer.greeting', array_column($candidates, 'id'), true)))
            ->willReturn([
                'intents' => [['intent_id' => 'answer.greeting', 'confidence' => .96]],
                'entities' => [],
                '_raw_response' => '{}',
            ]);
        $router = new RagBotService(
            $this->createMock(KnowledgeBaseService::class),
            new IntentDetectorService(),
            $groq,
            new PrewrittenResponseService(),
        );

        $result = $router->answer('Muy buenas gente', null, 'semantic_classifier', 'phone-a');

        $this->assertSame('answer.greeting', $result['sources'][0]['id']);
        $this->assertSame('routed', $result['source_type']);
    }

    /** @dataProvider greetingWithQuestionQueries */
    public function test_a_greeting_does_not_hide_a_concrete_question(string $query, string $expectedTopic): void
    {
        $result = $this->router()->answer($query, null, 'disabled', 'phone-a');

        $this->assertSame($expectedTopic, $result['sources'][0]['topic']);
    }

    public static function greetingWithQuestionQueries(): array
    {
        return [
            ['Hola, quiero hacer una reserva', 'lodging_reservation'],
            ['Buenas, ¿hay comida sin gluten?', 'gluten_free'],
        ];
    }

    private function router(): RagBotService
    {
        return new RagBotService(
            $this->createMock(KnowledgeBaseService::class),
            new IntentDetectorService(),
            $this->createMock(GroqChatService::class),
            new PrewrittenResponseService(),
        );
    }
}
