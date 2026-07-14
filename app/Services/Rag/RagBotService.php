<?php

namespace App\Services\Rag;

use App\Services\Bot\IntentDetectorService;
use App\Services\Bot\PrewrittenResponseService;
use App\Services\GroqChatService;
use Illuminate\Support\Facades\Log;

class RagBotService
{
    private const DEFAULT_TOP_K = 3;
    private const MAX_FRAGMENTS = 3;
    private const MAX_FRAGMENT_WORDS = 240;
    private const MAX_CONTEXT_WORDS = 700;
    private const MAX_HISTORY_ITEMS = 5;
    private const MIN_HISTORY_ITEMS = 3;
    private const FALLBACK_MESSAGE = 'No tengo esa información confirmada en la base de conocimiento del refugio. Ya di aviso al refugio para que puedan revisar tu consulta y evitar darte un dato incorrecto.';
    private const RESERVATION_GUIDANCE = 'Entiendo. Para ayudarte con eso necesito que tengas a mano el código de reserva o los datos con los que hiciste la reserva. Con eso se puede revisar el caso y ver si corresponde cancelación, reprogramación o reembolso según las condiciones vigentes.';
    private const RESCHEDULE_LINK = 'https://www.refugioagostinorocca.com/reschedule';

    private const SYSTEM_PROMPT = 'Eres el asistente virtual del Refugio Agostino Rocca. Respondés consultas por WhatsApp en español, breve, claro y directo. No saludes en cada respuesta: solo saluda si el usuario saluda o si es el primer mensaje de la conversación. Usá únicamente información del contexto e historial provistos. Si falta un dato para avanzar, pedilo de forma concreta. No inventes datos ni condiciones. No uses frases genéricas como "puedo ayudarte con información sobre...". Si el usuario pide cancelación, reprogramación, reembolso o temas de reserva, orientá al siguiente paso concreto. Si la consulta actual es aclaración/seguimiento, interpretala con el historial y reformulá más simple sin repetir todo. Máximo 120 palabras, salvo que el usuario pida detalle.';

    /** @var array<int,array{question:string,answer:string}> */
    private array $history = [];

    public function __construct(
        private readonly KnowledgeBaseService $knowledgeBaseService,
        private readonly IntentDetectorService $intentDetectorService,
        private readonly GroqChatService $groqChatService,
        private readonly PrewrittenResponseService $prewrittenResponseService,
    ) {
    }

    public function answer(string $question, ?int $limit = null, bool $useGenerativeAi = true): array
    {
        Log::info('Pregunta recibida en bot RAG.', ['question' => $question]);

        $intent = $this->intentDetectorService->detect($question);
        Log::info('Intent detectado.', ['intent' => $intent['intent'] ?? 'none']);

        if ($intent !== null && in_array($intent['intent'], ['cancel_reservation', 'refund_request'], true)) {
            Log::info('Se usó respuesta preescrita por intent de reclamo/reserva.', ['intent' => $intent['intent']]);

            return $this->finalizePrewrittenResponse($question, 'reclamo_reserva_terminos', 'prewritten_intent', '', ['respuesta preescrita']);
        }

        if ($intent !== null && $intent['intent'] === 'reschedule_reservation') {
            Log::info('Se usó respuesta preescrita por intent de reprogramación.', ['intent' => $intent['intent']]);

            return $this->finalizePrewrittenResponse($question, 'reprogramar_reserva', 'prewritten_intent', '', ['respuesta preescrita']);
        }

        if ($intent !== null && $intent['intent'] === 'clarification' && $this->recentHistory() !== []) {
            Log::info('Se usó historial para aclaración sin redacción generativa.', ['history_items' => count($this->recentHistory())]);
            $history = $this->recentHistory();
            $lastAnswer = (string) ($history[array_key_last($history)]['answer'] ?? self::FALLBACK_MESSAGE);

            return $this->finalizeResponse($question, $lastAnswer, [], 'history', '', ['historial']);
        }

        if ($this->shouldAnswerLocally($intent)) {
            Log::info('Se usó respuesta local por intent.', ['intent' => $intent['intent']]);
            return $this->finalizeResponse($question, $intent['response'], [], 'intent', '', ['respuesta automática']);
        }

        $prewrittenKey = $this->prewrittenResponseService->detect($question);
        if ($prewrittenKey !== null) {
            Log::info('Se usó respuesta preescrita por palabras clave.', ['response_key' => $prewrittenKey]);

            return $this->finalizePrewrittenResponse($question, $prewrittenKey, 'prewritten_keyword', '', ['respuesta preescrita']);
        }

        if ($useGenerativeAi) {
            $prewrittenKey = $this->classifyPrewrittenResponse($question);
            if ($prewrittenKey !== null) {
                Log::info('Groq encauzó a respuesta preescrita.', ['response_key' => $prewrittenKey]);

                return $this->finalizePrewrittenResponse($question, $prewrittenKey, 'prewritten_groq', '', ['respuesta preescrita']);
            }
        }

        $topK = min(self::MAX_FRAGMENTS, $this->resolveTopK($limit));
        $rawFragments = $this->retrieveRelevantChunks($question, $topK);
        Log::info('Cantidad de fragmentos recuperados.', ['count' => count($rawFragments)]);

        $fragments = $this->prepareFragments($rawFragments);
        $prompt = $this->buildPrompt($question, $fragments);

        if ($fragments === []) {
            Log::warning('Se usó fallback por falta de fragmentos relevantes.', ['fallback_reason' => 'no_fragments']);

            return $this->finalizeResponse($question, self::FALLBACK_MESSAGE, [], 'fallback', $prompt, []);
        }

        if (! $useGenerativeAi) {
            $prewrittenKey = $this->detectPrewrittenFromFragments($question, $fragments);
            if ($prewrittenKey !== null) {
                Log::info('Se usó respuesta preescrita sin IA generativa.', ['response_key' => $prewrittenKey]);

                return $this->finalizePrewrittenResponse($question, $prewrittenKey, 'prewritten_rag_extractive', $prompt, $this->sourcesFromFragments($fragments), $fragments);
            }

            Log::warning('Se usó fallback sin IA generativa porque no se pudo encauzar a una respuesta preescrita.', ['fallback_reason' => 'no_prewritten_match']);

            return $this->finalizeResponse($question, self::FALLBACK_MESSAGE, $fragments, 'fallback', $prompt, $this->sourcesFromFragments($fragments));
        }

        $prewrittenKey = $this->detectPrewrittenFromFragments($question, $fragments);
        if ($prewrittenKey !== null) {
            Log::info('Se usó respuesta preescrita a partir del contexto RAG.', ['response_key' => $prewrittenKey]);

            return $this->finalizePrewrittenResponse($question, $prewrittenKey, 'prewritten_rag', $prompt, $this->sourcesFromFragments($fragments), $fragments);
        }

        Log::warning('Se usó fallback porque no se pudo encauzar a una respuesta preescrita.', ['fallback_reason' => 'no_prewritten_match']);

        return $this->finalizeResponse($question, self::FALLBACK_MESSAGE, $fragments, 'fallback', $prompt, $this->sourcesFromFragments($fragments));
    }

    private function finalizePrewrittenResponse(string $question, string $key, string $sourceType, string $prompt, array $sources, array $fragments = []): array
    {
        $answer = $this->prewrittenResponseService->get($key) ?? self::FALLBACK_MESSAGE;

        return $this->finalizeResponse($question, $answer, $fragments, $sourceType, $prompt, $sources);
    }

    private function classifyPrewrittenResponse(string $question): ?string
    {
        $prompt = "Mensaje del cliente:\n{$question}\n\n"
            ."Elegí una sola clave de esta lista de respuestas preescritas:\n"
            .$this->prewrittenResponseService->keysForPrompt()."\n\n"
            ."Respondé únicamente JSON válido con este formato: {\"response_key\":\"clave\"}. "
            ."Si ninguna respuesta corresponde claramente, usá {\"response_key\":null}. No redactes una respuesta al cliente.";

        $raw = $this->groqChatService->generate(
            'Clasificás mensajes de WhatsApp del Refugio Agostino Rocca. No redactes respuestas: solo elegí una clave preescrita o null.',
            $prompt
        );

        if ($raw === null) {
            return null;
        }

        $decoded = json_decode($raw, true);
        $key = is_array($decoded) ? ($decoded['response_key'] ?? null) : null;

        if (! is_string($key) || $key === '' || $key === 'null') {
            return null;
        }

        return $this->prewrittenResponseService->isValidKey($key) ? $key : null;
    }

    private function detectPrewrittenFromFragments(string $question, array $fragments): ?string
    {
        $key = $this->prewrittenResponseService->detect($question);
        if ($key !== null) {
            return $key;
        }

        foreach ($fragments as $fragment) {
            $key = $this->prewrittenResponseService->detect($fragment['content'] ?? '');
            if ($key !== null) {
                return $key;
            }
        }

        return null;
    }

    private function buildExtractiveAnswer(array $fragments, ?array $intent): string
    {
        $answer = $this->truncateWords($fragments[0]['content'] ?? self::FALLBACK_MESSAGE, 90);

        if ($answer === '') {
            $answer = self::FALLBACK_MESSAGE;
        }

        if ($intent !== null && $intent['prepend_response'] !== null) {
            return $intent['prepend_response']."\n\n".$answer;
        }

        return $answer;
    }

    private function shouldAnswerLocally(?array $intent): bool
    {
        if ($intent === null || $intent['response'] === null) {
            return false;
        }

        return in_array($intent['intent'], ['saludo', 'despedida', 'agradecimiento', 'fallback_conversacional', 'comida_sin_gluten'], true);
    }

    private function finalizeResponse(string $question, string $answer, array $fragments, string $sourceType, string $prompt, array $sources): array
    {
        $this->pushHistory($question, $answer);

        return [
            'answer' => $answer,
            'sources' => $sources,
            'fragments' => $fragments,
            'source_type' => $sourceType,
            'prompt' => $prompt,
        ];
    }

    private function retrieveRelevantChunks(string $question, int $topK): array
    {
        return $this->knowledgeBaseService->search($question, $topK);
    }

    private function buildPrompt(string $question, array $fragments): string
    {
        $history = $this->recentHistory();
        $historyText = $history === []
            ? '(sin historial reciente)'
            : implode("\n", array_map(
                static fn (array $item, int $index): string => sprintf('%d) Usuario: %s | Asistente: %s', $index + 1, $item['question'], $item['answer']),
                $history,
                array_keys($history)
            ));

        $contextLines = array_map(
            static fn (array $fragment): string => $fragment['content'],
            $fragments
        );

        return "Pregunta del usuario:\n{$question}\n\n"
            ."Historial reciente:\n{$historyText}\n\n"
            ."Contexto recuperado de la base de conocimiento:\n".implode("\n\n", $contextLines)."\n\n"
            ."Instrucciones:\n"
            ."- Si la pregunta actual es una aclaración o seguimiento, interpretala usando el historial reciente.\n"
            ."- Respondé solamente usando el contexto.\n"
            ."- Si no hay información suficiente, decí que no tenés ese dato confirmado.\n"
            ."- No inventes horarios, precios, distancias, disponibilidad, servicios ni condiciones.\n"
            ."- No menciones \"según el fragmento\" ni \"según la base de conocimiento\".\n"
            ."- Respondé de forma natural, como si estuvieras contestando por WhatsApp.\n"
            ."- Si en el contexto aparece un enlace de reprogramación, incluilo cuando corresponda.\n"
            ."- Máximo 120 palabras.";
    }

    private function buildClarificationPrompt(string $question): string
    {
        $history = $this->recentHistory();
        $historyText = implode("\n", array_map(
            static fn (array $item): string => "Usuario: {$item['question']}\nBot: {$item['answer']}",
            $history
        ));

        return "Pregunta actual del usuario:\n{$question}\n\n"
            ."Historial reciente:\n{$historyText}\n\n"
            ."Instrucciones:\n"
            ."- Si la pregunta actual es una aclaración o seguimiento, interpretala usando el historial reciente.\n"
            ."- Reformulá la última explicación de forma más simple y concreta.\n"
            ."- No repitas toda la respuesta anterior si no hace falta.\n"
            ."- Si el tema es acceso, separá: Bariloche → Pampa Linda y Pampa Linda → Refugio.\n"
            ."- Máximo 120 palabras.";
    }

    private function resolveTopK(?int $limit): int
    {
        $configuredTopK = (int) env('RAG_TOP_K', self::DEFAULT_TOP_K);
        $defaultTopK = max(1, min(self::MAX_FRAGMENTS, $configuredTopK));

        return $limit === null ? $defaultTopK : max(1, min(self::MAX_FRAGMENTS, $limit));
    }

    private function prepareFragments(array $fragments): array
    {
        $prepared = [];
        $totalWords = 0;

        foreach (array_slice($fragments, 0, self::MAX_FRAGMENTS) as $fragment) {
            $trimmed = $this->truncateWords($fragment['content'], self::MAX_FRAGMENT_WORDS);
            $words = $this->countWords($trimmed);

            if ($words === 0 || $totalWords >= self::MAX_CONTEXT_WORDS) {
                continue;
            }

            $allowed = min($words, self::MAX_CONTEXT_WORDS - $totalWords);
            if ($allowed < $words) {
                $trimmed = $this->truncateWords($trimmed, $allowed);
                $words = $this->countWords($trimmed);
            }

            $fragment['content'] = $trimmed;
            $prepared[] = $fragment;
            $totalWords += $words;

            if ($totalWords >= self::MAX_CONTEXT_WORDS) {
                break;
            }
        }

        return $prepared;
    }

    private function recentHistory(): array
    {
        $maxItems = max(self::MIN_HISTORY_ITEMS, self::MAX_HISTORY_ITEMS);

        return array_slice($this->history, -$maxItems);
    }

    private function pushHistory(string $question, string $answer): void
    {
        $this->history[] = ['question' => $question, 'answer' => $answer];

        if (count($this->history) > self::MAX_HISTORY_ITEMS) {
            $this->history = array_slice($this->history, -self::MAX_HISTORY_ITEMS);
        }
    }

    private function truncateWords(string $text, int $maxWords): string
    {
        $words = preg_split('/\s+/u', trim($text)) ?: [];

        return trim(implode(' ', array_slice($words, 0, max(0, $maxWords))));
    }

    private function countWords(string $text): int
    {
        return count(preg_split('/\s+/u', trim($text)) ?: []);
    }

    private function sourcesFromFragments(array $fragments): array
    {
        return array_values(array_unique(array_map(
            static fn (array $fragment): string => sprintf('%s#chunk-%d', $fragment['filename'], $fragment['chunk_index']),
            $fragments
        )));
    }
}
