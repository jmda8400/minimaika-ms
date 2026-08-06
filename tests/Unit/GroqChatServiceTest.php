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
        $raw = '{"intents":[{"intent_id":"answer.reservation","confidence":0.91}],"entities":{"dates":[],"quantities":[],"codes":[]}}';
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => $raw]]]])]);

        $decision = (new GroqChatService())->routeApprovedResponse('¿Cómo reservo?', [[
            'id' => 'answer.reservation',
            'topic' => 'reservation',
            'description' => 'Cómo reservar',
            'score' => 0.8,
        ]]);

        $this->assertSame('answer.reservation', $decision['intents'][0]['intent_id']);
        $this->assertSame(0.91, $decision['intents'][0]['confidence']);
        $this->assertSame($raw, $decision['_raw_response']);
    }

    public function test_router_rejects_a_response_without_classifier_confidence(): void
    {
        config()->set('services.groq.api_key', 'test-key');
        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['content' => '{"intents":[{"intent_id":"answer.reservation"}]}']]],
        ])]);

        $decision = (new GroqChatService())->routeApprovedResponse('¿Cómo reservo?', [[
            'id' => 'answer.reservation',
            'topic' => 'reservation',
            'description' => 'Cómo reservar',
            'score' => 0.8,
        ]]);

        $this->assertNull($decision);
    }

    public function test_router_accepts_at_most_two_allowed_intents_and_entities(): void
    {
        config()->set('services.groq.api_key', 'test-key');
        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['content' => json_encode([
                'intents' => [
                    ['intent_id' => 'answer.reservation', 'confidence' => .94],
                    ['intent_id' => 'answer.payment', 'confidence' => .88],
                ],
                'entities' => ['dates' => ['15 de noviembre'], 'quantities' => ['2'], 'codes' => ['ABC123']],
            ])]]],
        ])]);

        $decision = (new GroqChatService())->routeApprovedResponse('Quiero reservar para 2 el 15 de noviembre y pagar ABC123', [
            ['id' => 'answer.reservation', 'topic' => 'reservation', 'description' => 'Cómo reservar', 'score' => .8],
            ['id' => 'answer.payment', 'topic' => 'payment', 'description' => 'Formas de pago', 'score' => .7],
        ]);

        $this->assertCount(2, $decision['intents']);
        $this->assertSame(['15 de noviembre'], $decision['entities']['dates']);
        $this->assertSame(['ABC123'], $decision['entities']['codes']);
    }

    public function test_router_rejects_more_than_two_intents(): void
    {
        config()->set('services.groq.api_key', 'test-key');
        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['content' => '{"intents":[{"intent_id":"answer.a","confidence":0.9},{"intent_id":"answer.b","confidence":0.8},{"intent_id":"answer.c","confidence":0.7}]}']]],
        ])]);

        $decision = (new GroqChatService())->routeApprovedResponse('tres consultas', [
            ['id' => 'answer.a', 'topic' => 'a', 'description' => 'A', 'score' => .8],
            ['id' => 'answer.b', 'topic' => 'b', 'description' => 'B', 'score' => .7],
            ['id' => 'answer.c', 'topic' => 'c', 'description' => 'C', 'score' => .6],
        ]);

        $this->assertNull($decision);
    }
}
