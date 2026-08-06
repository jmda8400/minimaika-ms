<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GroqChatService
{
    /**
     * @param array<int,array{id:string,topic:string,description:string,score:float}> $candidates
     * @return array{action:string,answer_id?:string,clarification_id?:string,fallback_id?:string}|null
     */
    public function routeApprovedResponse(string $question, array $candidates): ?array
    {
        if ($candidates === []) {
            return null;
        }

        $allowed = array_column($candidates, 'id');
        $content = $this->generate(
            'Sos un router. Nunca redactes una respuesta al usuario. Elegí exclusivamente un identificador provisto y devolvé JSON.',
            json_encode(['message' => $question, 'candidates' => array_map(static fn (array $candidate): array => [
                'id' => $candidate['id'], 'topic' => $candidate['topic'], 'description' => $candidate['description'],
            ], $candidates), 'schema' => ['action' => 'answer|clarify|fallback', 'answer_id' => 'id si action=answer', 'clarification_id' => 'id si action=clarify', 'fallback_id' => 'id si action=fallback']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '',
            ['temperature' => 0, 'max_tokens' => 80, 'top_p' => 1, 'response_format' => ['type' => 'json_object']],
        );
        $decision = json_decode((string) $content, true);
        if (! is_array($decision) || ! in_array($decision['action'] ?? null, ['answer', 'clarify', 'fallback'], true)) {
            Log::warning('Groq devolvió una decisión de router inválida.');

            return null;
        }
        $field = ['answer' => 'answer_id', 'clarify' => 'clarification_id', 'fallback' => 'fallback_id'][$decision['action']];
        if (! is_string($decision[$field] ?? null) || ! in_array($decision[$field], $allowed, true)) {
            Log::warning('Groq seleccionó un ID no permitido.', ['decision' => $decision]);

            return null;
        }

        return $decision;
    }

    public function generate(string $systemPrompt, string $userPrompt, array $options = []): ?string
    {
        $config = config('services.groq');
        $apiKey = (string) ($config['api_key'] ?? '');
        $baseUrl = (string) ($config['base_url'] ?? 'https://api.groq.com/openai/v1/chat/completions');
        $model = (string) ($config['model'] ?? 'llama-3.1-8b-instant');

        if ($apiKey === '' || $baseUrl === '') {
            Log::warning('Groq no configurado correctamente.');

            return null;
        }

        $payload = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $systemPrompt,
                ],
                [
                    'role' => 'user',
                    'content' => $userPrompt,
                ],
            ],
            'temperature' => $options['temperature'] ?? 0.2,
            'max_tokens' => $options['max_tokens'] ?? 220,
            'top_p' => $options['top_p'] ?? 0.9,
        ];
        if (isset($options['response_format'])) {
            $payload['response_format'] = $options['response_format'];
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout(15)
                ->post($baseUrl, $payload);

            if ($response->failed()) {
                Log::error('Error de respuesta Groq.', [
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);

                return null;
            }

            $content = trim((string) data_get($response->json(), 'choices.0.message.content', ''));

            return $content !== '' ? $content : null;
        } catch (Throwable $exception) {
            Log::error('Excepción al llamar a Groq.', [
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            return null;
        }
    }
}
