<?php

namespace App\Services\Rag;

use App\Services\Bot\IntentDetectorService;
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
    ) {
    }

    public function answer(string $question, ?int $limit = null, bool $useGenerativeAi = true): array
    {
        Log::info('Pregunta recibida en bot RAG.', ['question' => $question]);

        $intent = $this->intentDetectorService->detect($question);
        Log::info('Intent detectado.', ['intent' => $intent['intent'] ?? 'none']);

        if ($intent !== null && in_array($intent['intent'], ['cancel_reservation', 'refund_request'], true)) {
            Log::info('Se usó respuesta local por intent.', ['intent' => $intent['intent']]);

            return $this->finalizeResponse($question, self::RESERVATION_GUIDANCE, [], 'intent', '', ['respuesta automática']);
        }

        if ($intent !== null && $intent['intent'] === 'reschedule_reservation') {
            Log::info('Se usó respuesta local por intent.', ['intent' => $intent['intent']]);
            $answer = "Podés reprogramar tu reserva desde este enlace:\n".self::RESCHEDULE_LINK."\n\nAhí vas a poder gestionar el cambio de fecha de tu reserva. Te recomiendo tener a mano el código de reserva o los datos con los que hiciste la reserva.";

            return $this->finalizeResponse($question, $answer, [], 'intent', '', ['respuesta automática']);
        }

        if ($useGenerativeAi && $intent !== null && $intent['intent'] === 'clarification' && $this->recentHistory() !== []) {
            Log::info('Se usó historial para aclaración.', ['history_items' => count($this->recentHistory())]);
            $prompt = $this->buildClarificationPrompt($question);
            Log::info('Llamando a Groq para aclaración con historial.');
            $answer = $this->groqChatService->generate(self::SYSTEM_PROMPT, $prompt);

            if ($answer !== null) {
                return $this->finalizeResponse($question, $answer, [], 'history', $prompt, ['historial']);
            }
        }

        if ($this->shouldAnswerLocally($intent)) {
            Log::info('Se usó respuesta local por intent.', ['intent' => $intent['intent']]);
            return $this->finalizeResponse($question, $intent['response'], [], 'intent', '', ['respuesta automática']);
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
            Log::info('Se usó respuesta extractiva sin IA generativa.');

            return $this->finalizeResponse($question, $this->buildExtractiveAnswer($fragments, $intent), $fragments, 'rag_extractive', $prompt, $this->sourcesFromFragments($fragments));
        }

        Log::info('Llamando a Groq para redacción final.');
        $answer = $this->groqChatService->generate(self::SYSTEM_PROMPT, $prompt);

        if ($answer === null) {
            Log::error('Groq falló. Se usa fallback.');

            return $this->finalizeResponse($question, self::FALLBACK_MESSAGE, $fragments, 'fallback', $prompt, $this->sourcesFromFragments($fragments));
        }

        Log::info('Groq respondió correctamente.');

        if ($intent !== null && $intent['prepend_response'] !== null) {
            $answer = $intent['prepend_response']."\n\n".$answer;
        }

        return $this->finalizeResponse($question, $answer, $fragments, 'rag', $prompt, $this->sourcesFromFragments($fragments));
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
