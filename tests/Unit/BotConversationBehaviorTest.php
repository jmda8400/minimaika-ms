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
            'Hola' => 'exact', 'Test' => 'fallback', 'Agua' => 'clarification',
            '¿Hay agua en el refugio?' => 'exact', '¿Hay agua durante el sendero?' => 'exact',
            'Mate' => 'exact', 'flor azul satélite improbable' => 'fallback',
        ];

        foreach ($expectations as $message => $source) {
            $response = $bot->answer($message, null, false, '5492944000000');
            $this->assertSame($source, $response['source_type'], $message);
            $this->assertNotSame('', trim($response['answer']), $message);
            $this->assertIsString($response['answer'], $message);
        }
    }

    /** @dataProvider greetingMessages */
    public function test_greetings_receive_the_approved_welcome(string $message): void
    {
        $response = $this->bot()->answer($message, null, false, '5492944000000');

        $this->assertSame('greeting', $response['sources'][0]['topic']);
        $this->assertSame('¡Hola! Te comunicaste con el asistente virtual del Refugio Agostino Rocca. Puedo ayudarte con reservas, ubicación, acceso, horarios, servicios, caminatas, pagos y preguntas frecuentes. ¿En qué puedo ayudarte?', $response['answer']);
    }

    public static function greetingMessages(): array
    {
        return [
            ['Hola'], ['Holaa'], ['Holis'], ['¡Buen día!'], ['Buenas!'], ['¿Qué tal?'], ['¿Cómo estás?'], ['Saludos'],
        ];
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
        $this->assertStringStartsWith('No pude entender tu consulta.', $sameUser['answer']);
    }

    private function bot(?KnowledgeBaseService $knowledgeBase = null): RagBotService
    {
        $groq = Mockery::mock(GroqChatService::class);
        $groq->shouldNotReceive('generate');

        return new RagBotService($knowledgeBase ?? new KnowledgeBaseService(), new IntentDetectorService(), $groq, new PrewrittenResponseService());
    }
}
