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
    private const FALLBACK_MESSAGE = 'No tengo esa información confirmada en la base de conocimiento del refugio. Te recomiendo consultar directamente con el refugio para evitar darte un dato incorrecto.';

    private const SYSTEM_PROMPT = 'Eres el asistente virtual del Refugio Agostino Rocca. Respondés consultas de visitantes por WhatsApp. Tu estilo debe ser claro, amable, natural y breve. Usá únicamente la información provista en el contexto de la base de conocimiento. No inventes datos. Si la información no está en el contexto, decí que no tenés ese dato confirmado y sugerí consultar con el refugio. Respondé en español claro y cordial. No pegues fragmentos textuales largos. Resumí y explicá de forma conversacional. La respuesta debe tener entre 80 y 150 palabras como máximo, salvo que el usuario pida más detalle.';

    /** @var array<int,array{question:string,answer:string}> */
    private array $history = [];

    public function __construct(
        private readonly KnowledgeBaseService $knowledgeBaseService,
        private readonly IntentDetectorService $intentDetectorService,
        private readonly GroqChatService $groqChatService,
    ) {
    }

    public function answer(string $question, ?int $limit = null): array
    {
        Log::info('Pregunta recibida en bot RAG.', ['question' => $question]);

        $intent = $this->intentDetectorService->detect($question);
        if ($this->shouldAnswerLocally($intent)) {
            return $this->finalizeResponse($question, $intent['response'], [], 'intent', '', ['respuesta automática']);
        }

        $topK = min(self::MAX_FRAGMENTS, $this->resolveTopK($limit));
        $rawFragments = $this->retrieveRelevantChunks($question, $topK);
        Log::info('Cantidad de fragmentos recuperados.', ['count' => count($rawFragments)]);

        $fragments = $this->prepareFragments($rawFragments);
        $prompt = $this->buildPrompt($question, $fragments);

        if ($fragments === []) {
            Log::warning('Se usó fallback por falta de fragmentos relevantes.');

            return $this->finalizeResponse($question, self::FALLBACK_MESSAGE, [], 'rag', $prompt, []);
        }

        Log::info('Llamando a Groq para redacción final.');
        $answer = $this->groqChatService->generate(self::SYSTEM_PROMPT, $prompt);

        if ($answer === null) {
            Log::error('Groq falló. Se usa fallback.');

            return $this->finalizeResponse($question, self::FALLBACK_MESSAGE, $fragments, 'rag', $prompt, $this->sourcesFromFragments($fragments));
        }

        Log::info('Groq respondió correctamente.');

        if ($intent !== null && $intent['prepend_response'] !== null) {
            $answer = $intent['prepend_response']."\n\n".$answer;
        }

        return $this->finalizeResponse($question, $answer, $fragments, 'rag', $prompt, $this->sourcesFromFragments($fragments));
    }


    private function shouldAnswerLocally(?array $intent): bool
    {
        if ($intent === null || $intent['response'] === null) {
            return false;
        }

        return in_array($intent['intent'], ['saludo', 'despedida', 'agradecimiento', 'fallback_conversacional'], true);
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
            ."- Respondé solamente usando el contexto.\n"
            ."- Si no hay información suficiente, decí que no tenés ese dato confirmado.\n"
            ."- No inventes horarios, precios, distancias, disponibilidad, servicios ni condiciones.\n"
            ."- No menciones \"según el fragmento\" ni \"según la base de conocimiento\".\n"
            ."- Respondé de forma natural, como si estuvieras contestando por WhatsApp.\n"
            ."- Máximo 150 palabras.";
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
