<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GroqChatService
{
    public function generate(string $systemPrompt, string $userPrompt): ?string
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
            'temperature' => 0.2,
            'max_tokens' => 220,
            'top_p' => 0.9,
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
