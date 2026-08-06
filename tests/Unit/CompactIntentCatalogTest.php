<?php

namespace Tests\Unit;

use App\Services\Bot\ApprovedResponseCatalog;
use App\Services\Bot\IntentDetectorService;
use App\Services\Bot\PrewrittenResponseService;
use App\Services\GroqChatService;
use App\Services\Rag\KnowledgeBaseService;
use App\Services\Rag\RagBotService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CompactIntentCatalogTest extends TestCase
{
    public function test_catalog_is_compact_and_excludes_answers_names_and_duplicates(): void
    {
        Cache::flush();
        $catalog = new ApprovedResponseCatalog();
        $entries = $catalog->classifierEntries();
        $json = json_encode($entries, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $ids = array_column($entries, 'id');

        $this->assertLessThan(6000, strlen($json));
        $this->assertNotContains('answer.trail_water', $ids);
        $this->assertNotContains('answer.hot_water', $ids);
        $this->assertNotContains('answer.gluten_free', $ids);
        $this->assertNotContains('answer.meal_reservation', $ids);
        $this->assertContains('answer.agua_sendero_subida', $ids);
        foreach ($entries as $entry) {
            $this->assertSame(['id', 'description', 'examples'], array_keys($entry));
            $this->assertLessThanOrEqual(2, count($entry['examples']));
            $this->assertArrayNotHasKey('answer', $entry);
            $this->assertArrayNotHasKey('name', $entry);
        }
    }

    public function test_unique_exact_alias_avoids_groq_and_preserves_approved_answer(): void
    {
        $groq = $this->createMock(GroqChatService::class);
        $groq->expects($this->never())->method('routeApprovedResponse');
        $router = $this->router($groq);
        $result = $router->answer('Quiero reprogramar mi reserva');

        $this->assertSame('answer.reprogramar_reserva', $result['sources'][0]['id']);
        $this->assertSame((new ApprovedResponseCatalog())->active('answer.reprogramar_reserva')['answer'], $result['answer']);
    }

    public function test_greeting_plus_substantive_question_uses_groq(): void
    {
        $groq = $this->createMock(GroqChatService::class);
        $groq->expects($this->once())->method('routeApprovedResponse')->willReturn([
            'intent_id' => 'answer.agua_sendero_subida', 'confidence' => 1, 'entities' => [], '_raw_response' => '{}',
        ]);
        $result = $this->router($groq)->answer('¿Cómo están? Quería saber si había agua en el camino al refugio');

        $this->assertSame('answer.agua_sendero_subida', $result['sources'][0]['id']);
    }

    public function test_rate_limit_and_real_invalid_json_have_distinct_failure_types(): void
    {
        config()->set('services.groq.api_key', 'test');
        config()->set('services.groq.rate_limit_retry_max_seconds', 0);
        Http::fakeSequence()->push(['error' => ['type' => 'tokens', 'code' => 'rate_limit_exceeded']], 429, ['Retry-After' => '16']);
        $rateLimit = (new GroqChatService())->routeApprovedResponse('consulta', [['id' => 'answer.greeting', 'description' => 'Saludo']]);
        $this->assertSame('groq_rate_limit', $rateLimit['_groq_error']['type']);
        $this->assertSame(16, $rateLimit['_groq_error']['retry_after']);

        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'not json']]]])]);
        $invalid = (new GroqChatService())->routeApprovedResponse('consulta', [['id' => 'answer.greeting', 'description' => 'Saludo']]);
        $this->assertTrue($invalid['_parse_error']);
        $this->assertArrayNotHasKey('_groq_error', $invalid);
    }

    private function router(GroqChatService $groq): RagBotService
    {
        return new RagBotService($this->createMock(KnowledgeBaseService::class), new IntentDetectorService(), $groq, new PrewrittenResponseService());
    }
}
