<?php

namespace App\Services\Rag;

use App\Services\Bot\IntentDetectorService;
use App\Services\Bot\PrewrittenResponseService;
use App\Services\GroqChatService;
use Illuminate\Support\Facades\Log;

class RagBotService
{
    private const INITIAL_CANDIDATES = 8;
    private const MAX_FRAGMENTS = 3;
    private const MAX_HISTORY_ITEMS = 5;
    private const FALLBACK_MESSAGE = 'No pude entender tu consulta.';

    /** @var array<string,array<int,array{question:string,answer:string}>> */
    private array $historyByConversation = [];

    public function __construct(
        private readonly KnowledgeBaseService $knowledgeBaseService,
        private readonly IntentDetectorService $intentDetectorService,
        private readonly GroqChatService $groqChatService,
        private readonly PrewrittenResponseService $prewrittenResponseService,
    ) {
    }

    /**
     * Each invocation deliberately selects exactly one branch: local intent,
     * confident RAG, or fallback. Conversation history is keyed by phone.
     *
     * @return array{answer:string,sources:array,fragments:array,source_type:string,prompt:string}
     */
    public function answer(string $question, ?int $limit = null, bool|string $aiMode = 'semantic_classifier', ?string $conversationId = null): array
    {
        $aiMode = $this->normalizeAiMode($aiMode);
        $conversationId ??= '__default__';
        $question = trim($question);
        Log::info('Pregunta recibida en bot.', ['question' => $question, 'conversation_id' => $conversationId]);

        if (($clarification = $this->ambiguousResponse($question)) !== null) {
            return $this->finish($conversationId, $question, $clarification, [], 'clarification');
        }

        $intent = $this->intentDetectorService->detect($question);
        if ($intent !== null && $intent['response'] !== null && ! $intent['use_rag']) {
            Log::info('Rama local seleccionada.', ['intent' => $intent['intent']]);

            return $this->finish($conversationId, $question, $intent['response'], [], 'intent');
        }

        $prewrittenKey = $this->prewrittenResponseService->detect($question);
        if ($prewrittenKey !== null) {
            Log::info('Rama FAQ local seleccionada.', ['response_key' => $prewrittenKey]);

            return $this->finish($conversationId, $question, (string) $this->prewrittenResponseService->get($prewrittenKey), [], 'prewritten');
        }

        if ($this->isContextualFollowUp($question) && ($history = $this->recentHistory($conversationId)) !== []) {
            $previous = $history[array_key_last($history)]['answer'];
            Log::info('Rama de contexto seleccionada.', ['conversation_id' => $conversationId, 'history_items' => count($history)]);

            return $this->finish($conversationId, $question, $previous, [], 'history');
        }

        if ($aiMode === 'semantic_classifier' && ($classified = $this->classifyPrewrittenAnswer($question, $limit)) !== null) {
            return $this->finish($conversationId, $question, $classified['answer'], [], 'groq_classifier', '', [[
                'filename' => 'prewritten_responses',
                'chunk_index' => 0,
                'category' => 'prewritten',
                'topic' => $classified['key'],
                'location' => null,
            ]]);
        }

        $candidates = $this->knowledgeBaseService->search($question, $this->resolveCandidateLimit($limit));
        $fragments = $this->rerank($question, $candidates);
        $bestScore = (float) ($fragments[0]['rerank_score'] ?? 0);
        Log::info('Recuperación RAG evaluada.', [
            'question' => $question,
            'candidate_scores' => array_map(static fn (array $fragment): array => [
                'file' => $fragment['filename'], 'chunk' => $fragment['chunk_index'], 'retrieval_score' => $fragment['score'], 'rerank_score' => $fragment['rerank_score'],
            ], $fragments),
            'selected_fragment' => $fragments[0]['content'] ?? null,
            'best_score' => $bestScore,
            'threshold' => $this->confidenceThreshold(),
        ]);

        if ($fragments === [] || $bestScore < $this->confidenceThreshold()) {
            Log::info('Rama fallback seleccionada por confianza RAG insuficiente.', ['best_score' => $bestScore]);

            return $this->finish($conversationId, $question, self::FALLBACK_MESSAGE, [], 'fallback');
        }

        $sources = array_map(static fn (array $fragment): array => [
            'filename' => $fragment['filename'], 'chunk_index' => $fragment['chunk_index'], 'category' => $fragment['category'] ?? null,
            'topic' => $fragment['topic'] ?? null, 'location' => $fragment['location'] ?? null,
        ], $fragments);
        $answer = $this->extractiveAnswer($fragments);
        $prompt = $this->buildPrompt($question, $fragments, $this->recentHistory($conversationId));

        if ($aiMode === 'generative' && ($generated = $this->groqChatService->generate('Respondés consultas del Refugio Agostino Rocca usando exclusivamente el contexto entregado.', $prompt)) !== null) {
            $answer = $generated;
        }

        Log::info('Rama RAG seleccionada.', ['best_score' => $bestScore, 'fragment_count' => count($fragments)]);

        return $this->finish($conversationId, $question, $answer, $fragments, 'rag', $prompt, $sources);
    }

    /**
     * @return array{key:string,answer:string}|null
     */
    private function classifyPrewrittenAnswer(string $question, ?int $limit): ?array
    {
        $candidates = $this->prewrittenResponseService->candidatesForClassification($question, $this->resolveCandidateLimit($limit));
        $classification = $this->groqChatService->classifyPrewrittenResponse($question, $candidates);

        if ($classification === null || $classification['selected_key'] === null || $classification['confidence'] < $this->classifierConfidenceThreshold()) {
            Log::info('Clasificación Groq descartada por baja confianza o respuesta inválida.', [
                'confidence' => $classification['confidence'] ?? null,
                'threshold' => $this->classifierConfidenceThreshold(),
            ]);

            return null;
        }

        $answer = $this->prewrittenResponseService->get($classification['selected_key']);

        return $answer === null ? null : ['key' => $classification['selected_key'], 'answer' => $answer];
    }

    private function normalizeAiMode(bool|string $aiMode): string
    {
        if (is_bool($aiMode)) {
            return $aiMode ? 'generative' : 'disabled';
        }

        return in_array($aiMode, ['disabled', 'generative', 'semantic_classifier'], true) ? $aiMode : 'semantic_classifier';
    }

    private function ambiguousResponse(string $question): ?string
    {
        $normalized = $this->normalize($question);

        return match ($normalized) {
            'agua' => '¿Te referís al agua potable del refugio, al agua caliente para mate o al agua disponible durante el sendero?',
            'mate' => '¿Querés saber si hay agua caliente para mate en el refugio o si podés llevar mate durante el sendero?',
            'camino' => '¿Consultás por cómo llegar a Pampa Linda, los horarios del camino o la senda hasta el refugio?',
            'precio', 'precios' => '¿Querés consultar el precio del pernocte, las comidas, la ducha u otro servicio?',
            'reserva', 'reservar' => '¿Necesitás saber cómo reservar, modificar una reserva o consultar disponibilidad?',
            default => null,
        };
    }

    private function isContextualFollowUp(string $question): bool
    {
        $normalized = $this->normalize($question);

        return str_starts_with($normalized, 'y ') || str_starts_with($normalized, 'tambien ')
            || in_array($normalized, ['y cuanto cuesta', 'tambien en invierno', 'y en invierno'], true);
    }

    private function rerank(string $question, array $candidates): array
    {
        $tokens = array_values(array_filter(explode(' ', $this->normalize($question))));
        foreach ($candidates as &$candidate) {
            $content = $this->normalize($candidate['content']);
            $hits = count(array_filter($tokens, static fn (string $token): bool => mb_strlen($token) > 1 && str_contains($content, $token)));
            $directness = $hits / max(1, count($tokens));
            $candidate['rerank_score'] = round(((float) $candidate['score'] * 0.65) + ($directness * 0.35), 4);
        }
        unset($candidate);
        usort($candidates, static fn (array $a, array $b): int => $b['rerank_score'] <=> $a['rerank_score']);

        return array_slice($candidates, 0, self::MAX_FRAGMENTS);
    }

    private function extractiveAnswer(array $fragments): string
    {
        $content = trim((string) ($fragments[0]['content'] ?? ''));
        $sentences = preg_split('/(?<=[.!?])\s+/u', $content) ?: [];

        return trim(implode(' ', array_slice($sentences, 0, 3))) ?: self::FALLBACK_MESSAGE;
    }

    private function buildPrompt(string $question, array $fragments, array $history): string
    {
        $context = implode("\n\n", array_map(static fn (array $fragment): string => $fragment['content'], $fragments));
        $historyText = implode("\n", array_map(static fn (array $item): string => "Usuario: {$item['question']}\nBot: {$item['answer']}", $history));

        return "Pregunta: {$question}\n\nHistorial aplicable:\n".($historyText ?: '(ninguno)')."\n\nContexto:\n{$context}\n\nRespondé breve, en español, solo con información del contexto.";
    }

    private function finish(string $conversationId, string $question, string $answer, array $fragments, string $sourceType, string $prompt = '', array $sources = []): array
    {
        $this->historyByConversation[$conversationId][] = ['question' => $question, 'answer' => $answer];
        $this->historyByConversation[$conversationId] = array_slice($this->historyByConversation[$conversationId], -self::MAX_HISTORY_ITEMS);

        return compact('answer', 'sources', 'fragments', 'sourceType', 'prompt') + ['source_type' => $sourceType];
    }

    private function recentHistory(string $conversationId): array
    {
        return $this->historyByConversation[$conversationId] ?? [];
    }

    private function resolveCandidateLimit(?int $limit): int
    {
        return $limit === null ? max(1, (int) env('RAG_INITIAL_TOP_K', self::INITIAL_CANDIDATES)) : max(1, $limit);
    }

    private function confidenceThreshold(): float
    {
        return (float) env('RAG_CONFIDENCE_THRESHOLD', 0.55);
    }

    private function classifierConfidenceThreshold(): float
    {
        return (float) env('GROQ_CLASSIFIER_CONFIDENCE_THRESHOLD', 0.75);
    }

    private function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = strtr($text, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u']);
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text) ?? '';

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
