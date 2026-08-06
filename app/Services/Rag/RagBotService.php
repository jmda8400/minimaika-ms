<?php

namespace App\Services\Rag;

use App\Services\Bot\ApprovedResponseCatalog;
use App\Services\Bot\IntentDetectorService;
use App\Services\Bot\PrewrittenResponseService;
use App\Services\GroqChatService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RagBotService
{
    private const PENDING_SECONDS = 600;

    public function __construct(
        private readonly KnowledgeBaseService $knowledgeBaseService,
        private readonly IntentDetectorService $intentDetectorService,
        private readonly GroqChatService $groqChatService,
        private readonly PrewrittenResponseService $prewrittenResponseService,
        private readonly ?ApprovedResponseCatalog $catalog = null,
    ) {
    }

    /** @return array{answer:string,sources:array,fragments:array,source_type:string,prompt:string} */
    public function answer(string $question, ?int $limit = null, bool|string $aiMode = 'semantic_classifier', ?string $conversationId = null): array
    {
        $aiMode = is_bool($aiMode) ? ($aiMode ? 'semantic_classifier' : 'disabled') : ($aiMode === 'disabled' ? 'disabled' : 'semantic_classifier');
        $catalog = $this->catalog ?? new ApprovedResponseCatalog();
        $original = trim($question);
        $normalized = $this->normalize($original);
        $conversationId ??= '__default__';

        if (($pending = Cache::get($this->pendingKey($conversationId))) === 'clarify.water_type') {
            $followUps = ['en el sendero' => 'answer.trail_water', 'sendero' => 'answer.trail_water', 'potable' => 'answer.refuge_drinking_water', 'en el refugio' => 'answer.refuge_drinking_water', 'caliente' => 'answer.hot_water', 'mate' => 'answer.hot_water'];
            if (isset($followUps[$normalized])) {
                Cache::forget($this->pendingKey($conversationId));

                return $this->finish($catalog, $followUps[$normalized], 'clarification_resolved', $original, $normalized, [], [], null, 'pending_clarification');
            }
        }

        foreach ($catalog->all() as $record) {
            if (! $record['active']) {
                continue;
            }
            $phrases = array_merge([$record['canonical_question']], $record['aliases']);
            if (in_array($normalized, array_map($this->normalize(...), $phrases), true)) {
                if ($record['id'] === 'clarify.water_type') {
                    Cache::put($this->pendingKey($conversationId), $record['id'], self::PENDING_SECONDS);
                }

                return $this->finish($catalog, $record['id'], str_starts_with($record['id'], 'clarify.') ? 'clarification' : 'exact', $original, $normalized, [], [], null, 'exact_match');
            }
        }

        $candidateLimit = $limit ?? max(2, (int) config('services.rag.top_k', 12));
        $candidates = $this->hybridCandidates($normalized, $catalog, $candidateLimit);
        $best = (float) ($candidates[0]['score'] ?? 0);
        $minimumScore = (float) config('services.rag.retrieval_min_score', 0.12);

        // A small margin is expected for neighboring FAQ intents (for example,
        // reservations in general versus November). Let Groq disambiguate those
        // candidates instead of discarding a clear query before classification.
        if ($candidates === [] || $best < $minimumScore) {
            $id = str_contains($normalized, 'agua') ? 'clarify.water_type' : 'fallback.unknown';
            if ($id === 'clarify.water_type') {
                Cache::put($this->pendingKey($conversationId), $id, self::PENDING_SECONDS);
            }

            return $this->finish($catalog, $id, str_starts_with($id, 'clarify.') ? 'clarification' : 'fallback', $original, $normalized, $candidates, [], null, 'low_retrieval_score');
        }

        $routingCandidates = $candidates;
        foreach (['clarify.water_type', 'fallback.unknown'] as $controlId) {
            $control = $catalog->active($controlId);
            $routingCandidates[] = ['id' => $control['id'], 'topic' => $control['topic'], 'description' => $control['canonical_question'], 'score' => 0.0];
        }
        $decision = $aiMode === 'disabled' ? ['action' => 'answer', 'answer_id' => $candidates[0]['id'], 'confidence' => 1.0, '_raw_response' => 'classifier_disabled'] : $this->groqChatService->routeApprovedResponse($original, $routingCandidates);
        $id = $this->decisionId($decision);
        if ($id === null || $catalog->active($id) === null || ! in_array($id, array_column($routingCandidates, 'id'), true)) {
            return $this->finish($catalog, 'fallback.unknown', 'fallback', $original, $normalized, $candidates, $routingCandidates, $decision, 'invalid_or_inactive_id');
        }

        $confidence = (float) ($decision['confidence'] ?? 0);
        if ($confidence < (float) config('services.rag.classifier_confidence_threshold', 0.55)) {
            return $this->finish($catalog, 'fallback.unknown', 'fallback', $original, $normalized, $candidates, $routingCandidates, $decision, 'low_classifier_confidence');
        }

        return $this->finish($catalog, $id, 'routed', $original, $normalized, $candidates, $routingCandidates, $decision, 'approved_id');
    }

    private function hybridCandidates(string $message, ApprovedResponseCatalog $catalog, int $limit): array
    {
        $tokens = $this->searchTokens($message);
        $candidates = [];
        foreach ($catalog->all() as $record) {
            if (! $record['active'] || ! str_starts_with($record['id'], 'answer.')) {
                continue;
            }
            // Answers remain outside the index: retrieval can select approved IDs,
            // but user-visible copy can never leak into a generated response.
            $fields = array_filter([
                $record['canonical_question'],
                str_replace('_', ' ', $record['topic']),
                ...$record['aliases'],
            ]);
            $fieldScores = [];
            foreach ($fields as $field) {
                $normalizedField = $this->normalize($field);
                $fieldTokens = $this->searchTokens($normalizedField);
                $intersection = array_intersect($tokens, $fieldTokens);
                $coverage = count(array_unique($intersection)) / max(1, count(array_unique($tokens)));
                $precision = count(array_unique($intersection)) / max(1, count(array_unique($fieldTokens)));
                similar_text($message, $normalizedField, $similarity);
                $phraseBonus = str_contains($normalizedField, $message) || str_contains($message, $normalizedField) ? 0.15 : 0.0;
                $fieldScores[] = min(1.0, ($coverage * 0.55) + ($precision * 0.20) + (($similarity / 100) * 0.25) + $phraseBonus);
            }
            $score = round(max($fieldScores ?: [0.0]), 4);
            $candidates[] = ['id' => $record['id'], 'topic' => $record['topic'], 'description' => $record['canonical_question'], 'score' => $score];
        }
        usort($candidates, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($candidates, 0, max(2, $limit));
    }

    private function decisionId(?array $decision): ?string
    {
        if (! is_array($decision) || ! in_array($decision['action'] ?? null, ['answer', 'clarify', 'fallback'], true)) {
            return null;
        }

        return match ($decision['action']) {
            'answer' => is_string($decision['answer_id'] ?? null) ? $decision['answer_id'] : null,
            'clarify' => is_string($decision['clarification_id'] ?? null) ? $decision['clarification_id'] : null,
            'fallback' => is_string($decision['fallback_id'] ?? null) ? $decision['fallback_id'] : null,
        };
    }

    private function finish(ApprovedResponseCatalog $catalog, string $id, string $sourceType, string $original, string $normalized, array $candidates, array $sentCandidates, ?array $groqDecision, string $reason): array
    {
        $record = $catalog->active($id) ?? $catalog->active('fallback.unknown');
        Log::info('controlled_rag_classification', [
            'user_message' => $original,
            'normalized_message' => $normalized,
            'retrieved_candidate_ids' => array_column($candidates, 'id'),
            'retrieved_candidate_titles' => array_column($candidates, 'description'),
            'retrieval_scores' => array_column($candidates, 'score'),
            'candidates_sent_to_groq' => array_column($sentCandidates, 'id'),
            'groq_raw_response' => $groqDecision['_raw_response'] ?? null,
            'selected_intent_id' => $record['id'],
            'classifier_confidence' => isset($groqDecision['confidence']) ? (float) $groqDecision['confidence'] : null,
            'fallback_reason' => $reason,
        ]);

        return ['answer' => $record['approved_answer'], 'sources' => [['id' => $record['id'], 'topic' => $record['topic']]], 'fragments' => $candidates, 'source_type' => $sourceType, 'prompt' => ''];
    }

    /** @return array<int, string> */
    private function searchTokens(string $text): array
    {
        $stopWords = ['a', 'al', 'como', 'de', 'del', 'el', 'en', 'es', 'hago', 'la', 'las', 'lo', 'los', 'para', 'que', 'tal', 'un', 'una', 'y'];

        return array_values(array_filter(
            explode(' ', $this->normalize($text)),
            static fn (string $token): bool => mb_strlen($token) > 2 && ! in_array($token, $stopWords, true),
        ));
    }

    private function pendingKey(string $conversationId): string
    {
        return 'bot:pending-clarification:'.sha1($conversationId);
    }

    private function normalize(string $text): string
    {
        $text = strtr(mb_strtolower(trim($text)), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u']);

        return trim((string) preg_replace('/\s+/u', ' ', preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text) ?? ''));
    }
}
