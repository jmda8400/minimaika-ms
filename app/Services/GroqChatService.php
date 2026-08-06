<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GroqChatService
{
    /** @var array{type:string,body?:string,status?:int,message?:string}|null */
    private ?array $lastFailure = null;

    /**
     * Classify against the complete compact catalog. Validation deliberately
     * lives in RagBotService, which knows which active IDs were actually sent.
     *
     * @param array<int,array{id:string,name?:string,topic?:string,description:string,examples?:array}> $candidates
     * @return array<string,mixed>|null
     */
    public function routeApprovedResponse(string $question, array $candidates): ?array
    {
        if ($candidates === []) {
            return null;
        }

        $content = $this->generate(
            'Sos un clasificador de intenciones para el asistente del Refugio Agostino Rocca. Tu única tarea es elegir la intención que mejor represente el mensaje del usuario. No respondas la consulta. No redactes contenido para el usuario. No inventes políticas. No uses conocimiento externo. Elegí únicamente un ID incluido en el catálogo. Interpretá sinónimos, errores ortográficos y formas naturales de hablar. Una pregunta como "¿Cómo están?" es un saludo. Los datos como fechas, cantidades y códigos no cambian necesariamente la intención principal. Si ninguna intención corresponde razonablemente, devolvé unknown usando intent_id null y confidence 0. Respondé exclusivamente JSON válido con intent_id, confidence, reason y entities.',
            json_encode(['message' => $question, 'catalog' => $candidates, 'schema' => ['intent_id' => 'ID del catálogo o null', 'confidence' => 'número entre 0 y 1', 'reason' => 'solo para logs', 'entities' => new \stdClass()]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '',
            ['temperature' => 0, 'max_tokens' => 180, 'top_p' => 1, 'response_format' => ['type' => 'json_object']],
        );

        if ($content === null) {
            return [
                '_raw_response' => $this->lastFailure['body'] ?? null,
                '_parse_error' => true,
                '_groq_error' => $this->lastFailure,
            ];
        }

        $raw = $content;
        $json = preg_replace('/^\s*```(?:json)?\s*|\s*```\s*$/iu', '', trim($raw)) ?? trim($raw);
        $decision = json_decode(trim($json), true);
        if (! is_array($decision)) {
            Log::warning('Groq devolvió una decisión de clasificador inválida.', ['groq_raw_response' => $raw]);

            return ['_raw_response' => $raw, '_parse_error' => true];
        }

        // Backward-compatible parsing for the previous envelope. New Groq
        // prompts request one intent_id, but this keeps rolling deployments and
        // integrations deterministic while callers migrate.
        if (array_key_exists('intents', $decision)) {
            $intents = $decision['intents'];
            $allowed = array_map('strval', array_column($candidates, 'id'));
            if (! is_array($intents) || $intents === [] || count($intents) > 2) {
                return null;
            }
            foreach ($intents as $intent) {
                if (! is_array($intent) || ! array_key_exists('intent_id', $intent) || ! is_numeric($intent['confidence'] ?? null) || ! in_array((string) $intent['intent_id'], $allowed, true)) {
                    return null;
                }
            }
        }

        $decision['entities'] = is_array($decision['entities'] ?? null) ? $decision['entities'] : [];
        $decision['_raw_response'] = $raw;

        return $decision;
    }

    public function generate(string $systemPrompt, string $userPrompt, array $options = []): ?string
    {
        $this->lastFailure = null;
        $config = config('services.groq');
        $apiKey = (string) ($config['api_key'] ?? '');
        $baseUrl = (string) ($config['base_url'] ?? 'https://api.groq.com/openai/v1/chat/completions');
        $model = (string) ($config['model'] ?? 'llama-3.1-8b-instant');
        $timeout = (int) ($config['timeout'] ?? 15);

        if ($apiKey === '' || $baseUrl === '') {
            Log::warning('Groq no configurado correctamente.');
            $this->lastFailure = ['type' => 'configuration'];

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
            if (app()->isLocal() || config('app.debug')) {
                Log::debug('Petición HTTP a Groq', [
                    'url' => $baseUrl,
                    'model' => $model,
                    'api_key_configured' => $apiKey !== '',
                    'payload' => $payload,
                    'timeout' => $timeout,
                    'content_path' => 'choices.0.message.content',
                ]);
            }

            $response = Http::withToken($apiKey)
                ->timeout($timeout)
                ->post($baseUrl, $payload);

            if (app()->isLocal() || config('app.debug')) {
                Log::debug('Respuesta HTTP de Groq', [
                    'status' => $response->status(),
                    'successful' => $response->successful(),
                    'content_type' => $response->header('Content-Type'),
                    'body' => $response->body(),
                    'json' => $response->json(),
                ]);
            }

            if ($response->failed()) {
                $this->lastFailure = [
                    'type' => 'http',
                    'status' => $response->status(),
                    'body' => $response->body(),
                ];
                Log::error('Error de respuesta Groq.', [
                    'status' => $response->status(),
                    'content_type' => $response->header('Content-Type'),
                    'body' => $response->body(),
                    'json' => $response->json(),
                ]);

                return null;
            }

            $content = data_get($response->json(), 'choices.0.message.content');
            if (! is_string($content) || trim($content) === '') {
                $this->lastFailure = [
                    'type' => 'missing_content',
                    'status' => $response->status(),
                    'body' => $response->body(),
                ];
                Log::error('La respuesta de Groq no contiene contenido utilizable.', [
                    'status' => $response->status(),
                    'content_path' => 'choices.0.message.content',
                    'body' => $response->body(),
                    'json' => $response->json(),
                ]);

                return null;
            }

            return trim($content);
        } catch (Throwable $exception) {
            $this->lastFailure = [
                'type' => 'exception',
                'message' => $exception->getMessage(),
            ];
            Log::error('Excepción al llamar a Groq.', [
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            return null;
        }
    }
}
