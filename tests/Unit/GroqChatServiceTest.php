<?php

namespace Tests\Unit;

use App\Services\GroqChatService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GroqChatServiceTest extends TestCase
{
    public function test_classifies_a_candidate_with_strict_json(): void
    {
        Config::set('services.groq.api_key', 'test-key');
        Config::set('services.groq.base_url', 'https://groq.test/chat');

        Http::fake([
            'https://groq.test/chat' => Http::response([
                'choices' => [[
                    'message' => ['content' => json_encode([
                        'selected_key' => 'clima',
                        'confidence' => 0.92,
                        'reason' => 'Consulta sobre pronóstico.',
                    ])],
                ]],
            ]),
        ]);

        $classification = (new GroqChatService())->classifyPrewrittenResponse('¿Cómo está el clima?', [
            'clima' => [
                'title' => 'CLIMA',
                'response' => 'Te comparto el link que usamos para ver el pronóstico puntual del refugio: www.windguru.cz/489881',
                'keywords' => ['clima'],
                'score' => 1.0,
            ],
        ]);

        $this->assertSame('clima', $classification['selected_key']);
        $this->assertSame(0.92, $classification['confidence']);

        Http::assertSent(fn ($request): bool => $request['temperature'] === 0 && $request['max_tokens'] === 120);
    }

    public function test_rejects_invalid_json(): void
    {
        Config::set('services.groq.api_key', 'test-key');
        Config::set('services.groq.base_url', 'https://groq.test/chat');

        Http::fake(['https://groq.test/chat' => Http::response(['choices' => [['message' => ['content' => 'clima']]]])]);

        $this->assertNull((new GroqChatService())->classifyPrewrittenResponse('clima', [
            'clima' => ['title' => 'CLIMA', 'response' => 'Respuesta exacta', 'keywords' => ['clima'], 'score' => 1.0],
        ]));
    }

    public function test_rejects_unknown_key(): void
    {
        Config::set('services.groq.api_key', 'test-key');
        Config::set('services.groq.base_url', 'https://groq.test/chat');

        Http::fake(['https://groq.test/chat' => Http::response(['choices' => [['message' => ['content' => '{"selected_key":"otra","confidence":0.99}']]]])]);

        $this->assertNull((new GroqChatService())->classifyPrewrittenResponse('clima', [
            'clima' => ['title' => 'CLIMA', 'response' => 'Respuesta exacta', 'keywords' => ['clima'], 'score' => 1.0],
        ]));
    }
}
