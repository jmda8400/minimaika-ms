<?php

namespace Tests\Unit;

use App\Services\Bot\IntentDetectorService;
use App\Services\Bot\PrewrittenResponseService;
use App\Services\GroqChatService;
use App\Services\Rag\KnowledgeBaseService;
use App\Services\Rag\RagBotService;
use Mockery;
use Tests\TestCase;

class BotConversationBehaviorTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_common_messages_take_one_deterministic_branch(): void
    {
        $bot = $this->bot();
        $expectations = [
            'Hola' => 'intent', 'Test' => 'fallback', 'Agua' => 'clarification',
            '¿Hay agua en el refugio?' => 'prewritten', '¿Hay agua durante el sendero?' => 'prewritten',
            'Mate' => 'clarification', '¿Cómo hago para reservar?' => 'intent', '¿Y cuánto cuesta?' => 'prewritten',
            'flor azul satélite improbable' => 'fallback',
        ];

        foreach ($expectations as $message => $source) {
            $response = $bot->answer($message, null, false, '5492944000000');
            $this->assertSame($source, $response['source_type'], $message);
            $this->assertNotSame('', trim($response['answer']), $message);
            $this->assertIsString($response['answer'], $message);
        }
    }

    public function test_ambiguous_water_asks_for_clarification_without_searching(): void
    {
        $knowledgeBase = Mockery::mock(KnowledgeBaseService::class);
        $knowledgeBase->shouldNotReceive('search');
        $bot = $this->bot($knowledgeBase);

        $response = $bot->answer('agua', null, false, '5492944000000');

        $this->assertSame('clarification', $response['source_type']);
        $this->assertStringContainsString('agua potable', $response['answer']);
    }

    public function test_history_is_isolated_by_phone_and_random_text_does_not_inherit_it(): void
    {
        $bot = $this->bot();
        $bot->answer('¿Hay agua en el refugio?', null, false, '5492944000000');

        $otherUser = $bot->answer('test', null, false, '5492944111111');
        $sameUser = $bot->answer('texto aleatorio sin relacion', null, false, '5492944000000');

        $this->assertSame('fallback', $otherUser['source_type']);
        $this->assertSame('fallback', $sameUser['source_type']);
        $this->assertSame('No pude entender tu consulta.', $sameUser['answer']);
    }

    private function bot(?KnowledgeBaseService $knowledgeBase = null): RagBotService
    {
        $groq = Mockery::mock(GroqChatService::class);
        $groq->shouldNotReceive('generate');

        return new RagBotService($knowledgeBase ?? new KnowledgeBaseService(), new IntentDetectorService(), $groq, new PrewrittenResponseService());
    }
}
