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
