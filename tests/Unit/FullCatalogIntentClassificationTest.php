<?php

namespace Tests\Unit;

use App\Services\Bot\ApprovedResponseCatalog;
use App\Services\Bot\IntentDetectorService;
use App\Services\Bot\PrewrittenResponseService;
use App\Services\GroqChatService;
use App\Services\Rag\KnowledgeBaseService;
use App\Services\Rag\RagBotService;
use Tests\TestCase;

class FullCatalogIntentClassificationTest extends TestCase
{
    /** @dataProvider greetingQueries */
    public function test_natural_greetings_are_classified_from_the_full_catalog(string $message): void
    {
        $result = $this->routerSelecting('answer.greeting')->answer($message);

        $this->assertSame('answer.greeting', $result['sources'][0]['id']);
        $this->assertSame((new ApprovedResponseCatalog())->active('answer.greeting')['answer'], $result['answer']);
        $this->assertSame('exact_alias', $result['debug']['resolution']);
    }

    public static function greetingQueries(): array
    {
        return [['Hola'], ['¿Cómo están?'], ['Hola, ¿cómo están?'], ['Buenas']];
    }

    public function test_reprogramming_returns_the_literal_official_answer(): void
    {
        $result = $this->routerSelecting('answer.reprogramar_reserva')->answer('Quiero cambiar la fecha de mi reserva');
        $official = (new ApprovedResponseCatalog())->active('answer.reprogramar_reserva')['answer'];

        $this->assertSame($official, $result['answer']);
    }

    public function test_booking_is_distinct_from_reprogramming(): void
    {
        $result = $this->routerSelecting('answer.lodging_reservation')->answer('Quiero reservar para septiembre');

        $this->assertSame('lodging_reservation', $result['sources'][0]['topic']);
    }

    public function test_id_outside_catalog_and_low_confidence_are_rejected(): void
    {
        $invalid = $this->routerSelecting('answer.not_sent')->answer('consulta');
        $low = $this->routerSelecting('answer.greeting', .64)->answer('Hola');

        $this->assertSame('fallback', $invalid['source_type']);
        $this->assertSame('invalid_or_inactive_id', $invalid['debug']['fallback_reason']);
        $this->assertSame('fallback', $low['source_type']);
        $this->assertSame('low_classifier_confidence', $low['debug']['fallback_reason']);
    }

    public function test_unknown_uses_fallback_and_cancellation_placeholder_is_not_selectable(): void
    {
        $result = $this->routerSelecting(null, 0)->answer('teletransportación interestelar');
        $catalog = new ApprovedResponseCatalog();

        $this->assertSame('fallback', $result['source_type']);
        $this->assertSame($catalog->active('fallback.unknown')['answer'], $result['answer']);
        $this->assertNull($catalog->active('answer.cancelar_reserva'));
        $this->assertNotContains('answer.cancelar_reserva', $result['debug']['intent_ids_sent_to_groq']);
    }

    private function routerSelecting(?string $id, float $confidence = .97): RagBotService
    {
        $groq = $this->createMock(GroqChatService::class);
        $groq->method('routeApprovedResponse')
            ->with($this->isType('string'), $this->callback(function (array $entries): bool {
                return count($entries) === count((new ApprovedResponseCatalog())->classifierEntries());
            }))
            ->willReturn(['intent_id' => $id, 'confidence' => $confidence, 'reason' => 'test', 'entities' => [], '_raw_response' => '{}']);

        return new RagBotService(
            $this->createMock(KnowledgeBaseService::class),
            new IntentDetectorService(),
            $groq,
            new PrewrittenResponseService(),
        );
    }
}
