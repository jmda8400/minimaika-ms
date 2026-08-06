<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GroqChatService
{
    /**
     * @param array<int,array{id:string,topic:string,description:string,score:float}> $candidates
     * @return array{intents:array<int,array{intent_id:string,confidence:float}>,entities:array<string,mixed>,_raw_response:string}|null
     */
    public function routeApprovedResponse(string $question, array $candidates): ?array
    {
        if ($candidates === []) {
            return null;
        }

        $allowed = array_column($candidates, 'id');
        $content = $this->generate(
            'Sos exclusivamente un clasificador de intenciones. Interpretá lenguaje natural, sinónimos y errores ortográficos. Detectá una o, solamente si el mensaje realmente contiene dos consultas, hasta dos intenciones. Si el mensaje combina un saludo con una consulta concreta, elegí solamente la intención de la consulta y no la de saludo. Extraé fechas, cantidades y códigos literalmente presentes. Nunca respondas la consulta, redactes texto para el usuario, completes datos faltantes ni uses conocimiento externo. Elegí exclusivamente identificadores provistos; si ninguno corresponde elegí fallback. Devolvé solamente el JSON solicitado.',
            json_encode(['message' => $question, 'candidates' => array_map(static fn (array $candidate): array => [
                'id' => $candidate['id'], 'topic' => $candidate['topic'], 'description' => $candidate['description'],
            ], $candidates), 'schema' => ['intents' => [['intent_id' => 'uno de los IDs provistos', 'confidence' => 'número entre 0 y 1']], 'entities' => ['dates' => [], 'quantities' => [], 'codes' => []]], 'constraints' => ['min_intents' => 1, 'max_intents' => 2]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '',
            ['temperature' => 0, 'max_tokens' => 180, 'top_p' => 1, 'response_format' => ['type' => 'json_object']],
        );
        $decision = json_decode((string) $content, true);
        $intents = $decision['intents'] ?? null;
        if (! is_array($decision) || ! is_array($intents) || count($intents) < 1 || count($intents) > 2) {
            Log::warning('Groq devolvió una decisión de router inválida.', ['groq_raw_response' => $content]);

            return null;
        }

        $seen = [];
        foreach ($intents as $index => $intent) {
            $id = $intent['intent_id'] ?? null;
            if (! is_array($intent) || ! is_string($id) || ! in_array($id, $allowed, true) || isset($seen[$id]) || ! is_numeric($intent['confidence'] ?? null)) {
                Log::warning('Groq seleccionó una intención inválida o no permitida.', ['decision' => $decision, 'groq_raw_response' => $content]);

                return null;
            }
            $seen[$id] = true;
            $decision['intents'][$index]['confidence'] = max(0.0, min(1.0, (float) $intent['confidence']));
        }

        $decision['entities'] = is_array($decision['entities'] ?? null) ? $decision['entities'] : [];
        $decision['_raw_response'] = (string) $content;

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
