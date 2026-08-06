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

    public function test_rag_uses_the_model_only_after_a_confident_retrieval(): void
    {
        $knowledgeBase = Mockery::mock(KnowledgeBaseService::class);
        $knowledgeBase->shouldReceive('search')->once()->with('¿Dónde queda el refugio?', 8)->andReturn([[
            'filename' => 'ubicacion.md', 'chunk_index' => 0,
            'content' => 'El Refugio Agostino Rocca está en Pampa Linda, en el Cerro Tronador.',
            'score' => 0.9, 'category' => 'ubicacion', 'topic' => 'Ubicación', 'location' => 'Pampa Linda',
        ]]);

        $groq = Mockery::mock(GroqChatService::class);
        $groq->shouldReceive('generate')
            ->once()
            ->withArgs(function (string $systemPrompt, string $userPrompt): bool {
                return str_contains($systemPrompt, 'exclusivamente el contexto')
                    && str_contains($userPrompt, '¿Dónde queda el refugio?');
            })
            ->andReturn('Está en Pampa Linda, en el Cerro Tronador.');

        $service = new RagBotService(
            $knowledgeBase,
            new IntentDetectorService(),
            $groq,
            new PrewrittenResponseService(),
        );

        $response = $service->answer('¿Dónde queda el refugio?', null, 'generative');

        $this->assertSame('rag', $response['source_type']);
        $this->assertSame('Está en Pampa Linda, en el Cerro Tronador.', $response['answer']);
    }


    public function test_semantic_classifier_returns_exact_prewritten_answer_without_using_groq_text(): void
    {
        $knowledgeBase = Mockery::mock(KnowledgeBaseService::class);
        $knowledgeBase->shouldNotReceive('search');

        $groq = Mockery::mock(GroqChatService::class);
        $groq->shouldNotReceive('generate');
        $groq->shouldReceive('classifyPrewrittenResponse')
            ->once()
            ->withArgs(fn (string $question, array $candidates): bool => $question === 'Necesito pronóstico meteorológico' && array_key_exists('clima', $candidates))
            ->andReturn(['selected_key' => 'clima', 'confidence' => 0.91, 'reason' => 'Habla del pronóstico.']);

        $prewritten = new PrewrittenResponseService();
        $service = new RagBotService(
            $knowledgeBase,
            new IntentDetectorService(),
            $groq,
            $prewritten,
        );

        $response = $service->answer('Necesito pronóstico meteorológico', null, 'semantic_classifier');

        $this->assertSame('groq_classifier', $response['source_type']);
        $this->assertSame($prewritten->get('clima'), $response['answer']);
        $this->assertNotSame('Habla del pronóstico.', $response['answer']);
    }

    public function test_semantic_classifier_falls_back_when_confidence_is_low(): void
    {
        $knowledgeBase = Mockery::mock(KnowledgeBaseService::class);
        $knowledgeBase->shouldReceive('search')->once()->andReturn([]);

        $groq = Mockery::mock(GroqChatService::class);
        $groq->shouldReceive('classifyPrewrittenResponse')->once()->andReturn(['selected_key' => 'clima', 'confidence' => 0.2, 'reason' => null]);
        $groq->shouldNotReceive('generate');

        $service = new RagBotService(
            $knowledgeBase,
            new IntentDetectorService(),
            $groq,
            new PrewrittenResponseService(),
        );

        $response = $service->answer('Necesito pronóstico meteorológico', null, 'semantic_classifier');

        $this->assertSame('fallback', $response['source_type']);
        $this->assertSame('No pude entender tu consulta.', $response['answer']);
    }

    public function test_tac_question_uses_prewritten_gluten_answer_without_groq(): void
    {
        $knowledgeBase = Mockery::mock(KnowledgeBaseService::class);
        $knowledgeBase->shouldNotReceive('search');

        $groq = Mockery::mock(GroqChatService::class);
        $groq->shouldNotReceive('generate');
        $groq->shouldNotReceive('classifyPrewrittenResponse');

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
