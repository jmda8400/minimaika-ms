<?php

namespace Tests\Unit;

use App\Services\GroqChatService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GroqChatServiceTest extends TestCase
{
    public function test_router_returns_only_an_allowed_id_with_confidence_and_raw_response(): void
    {
        config()->set('services.groq.api_key', 'test-key');
        $raw = '{"action":"answer","answer_id":"answer.reservation","confidence":0.91}';
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => $raw]]]])]);

        $decision = (new GroqChatService())->routeApprovedResponse('¿Cómo reservo?', [[
            'id' => 'answer.reservation',
            'topic' => 'reservation',
            'description' => 'Cómo reservar',
            'score' => 0.8,
        ]]);

        $this->assertSame('answer.reservation', $decision['answer_id']);
        $this->assertSame(0.91, $decision['confidence']);
        $this->assertSame($raw, $decision['_raw_response']);
    }

    public function test_router_rejects_a_response_without_classifier_confidence(): void
    {
        config()->set('services.groq.api_key', 'test-key');
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => '{"action":"answer","answer_id":"answer.reservation"}']]]])]);

        $decision = (new GroqChatService())->routeApprovedResponse('¿Cómo reservo?', [[
            'id' => 'answer.reservation',
            'topic' => 'reservation',
            'description' => 'Cómo reservar',
            'score' => 0.8,
        ]]);

        $this->assertNull($decision);
    }
}
