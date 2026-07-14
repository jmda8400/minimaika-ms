<?php

namespace Tests\Unit;

use App\Services\Bot\IntentDetectorService;
use App\Services\Bot\PrewrittenResponseService;
use App\Services\GroqChatService;
use App\Services\Rag\KnowledgeBaseService;
use App\Services\Rag\RagBotService;
use Mockery;
use Tests\TestCase;

class RagBotServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_groq_only_classifies_to_a_prewritten_response(): void
    {
        $knowledgeBase = Mockery::mock(KnowledgeBaseService::class);
        $knowledgeBase->shouldNotReceive('search');

        $groq = Mockery::mock(GroqChatService::class);
        $groq->shouldReceive('generate')
            ->once()
            ->withArgs(function (string $systemPrompt, string $userPrompt): bool {
                return str_contains($systemPrompt, 'No redactes respuestas')
                    && str_contains($userPrompt, 'response_key')
                    && str_contains($userPrompt, 'horario_camino_pampa_linda');
            })
            ->andReturn('{"response_key":"horario_camino_pampa_linda"}');

        $service = new RagBotService(
            $knowledgeBase,
            new IntentDetectorService(),
            $groq,
            new PrewrittenResponseService(),
        );

        $response = $service->answer('A qué hora se puede pasar?', null, true);

        $this->assertSame('prewritten_groq', $response['source_type']);
        $this->assertStringContainsString('Subida únicamente', $response['answer']);
    }

    public function test_tac_question_uses_prewritten_gluten_answer_without_groq(): void
    {
        $knowledgeBase = Mockery::mock(KnowledgeBaseService::class);
        $knowledgeBase->shouldNotReceive('search');

        $groq = Mockery::mock(GroqChatService::class);
        $groq->shouldNotReceive('generate');

        $service = new RagBotService(
            $knowledgeBase,
            new IntentDetectorService(),
            $groq,
            new PrewrittenResponseService(),
        );

        $response = $service->answer('Tienen comida sin TAC?', null, true);

        $this->assertSame('intent', $response['source_type']);
        $this->assertStringContainsString('contaminación cruzada', $response['answer']);
    }
}
