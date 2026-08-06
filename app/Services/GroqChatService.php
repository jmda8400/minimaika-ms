<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GroqChatService
{

    /**
     * @param array<string, array{title:string,response:string,keywords:array<int,string>,score:float}> $candidates
     * @return array{selected_key:?string,confidence:float,reason:?string}|null
     */
    public function classifyPrewrittenResponse(string $question, array $candidates): ?array
    {
        if ($candidates === []) {
            return null;
        }

        $candidateText = implode("\n", array_map(
            static fn (string $key, array $item): string => json_encode([
                'key' => $key,
                'title' => $item['title'],
                'keywords' => $item['keywords'],
                'response_excerpt' => mb_substr($item['response'], 0, 500),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '',
            array_keys($candidates),
            $candidates,
        ));

        $prompt = "Pregunta del usuario: {$question}\n\nCandidatas disponibles, una por línea en JSON:\n{$candidateText}\n\nDevolvé solamente JSON válido con selected_key, confidence y reason. selected_key debe ser una key candidata o null. No redactes respuesta final.";
        $content = $this->generate(
            'Sos un clasificador semántico. Elegís cuál respuesta preescrita corresponde. No redactás, no resumís ni completás respuestas.',
            $prompt,
            ['temperature' => 0, 'max_tokens' => 120, 'top_p' => 1],
        );

        if ($content === null) {
            return null;
        }

        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            Log::warning('Groq devolvió una clasificación no JSON.', ['content' => $content]);

            return null;
        }

        $selectedKey = $decoded['selected_key'] ?? null;
        $confidence = (float) ($decoded['confidence'] ?? 0);

        if ($selectedKey !== null && (! is_string($selectedKey) || ! array_key_exists($selectedKey, $candidates))) {
            Log::warning('Groq seleccionó una respuesta inexistente.', ['selected_key' => $selectedKey]);

            return null;
        }

        if ($confidence < 0 || $confidence > 1) {
            return null;
        }

        return [
            'selected_key' => $selectedKey,
            'confidence' => $confidence,
            'reason' => isset($decoded['reason']) ? (string) $decoded['reason'] : null,
        ];
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
