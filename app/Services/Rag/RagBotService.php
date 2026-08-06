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
    private const MIN_SCORE = 0.34;
    private const MIN_MARGIN = 0.12;
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

                return $this->finish($catalog, $followUps[$normalized], 'clarification_resolved', $original, $normalized, [], null, 'pending_clarification');
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

                return $this->finish($catalog, $record['id'], str_starts_with($record['id'], 'clarify.') ? 'clarification' : 'exact', $original, $normalized, [], null, 'exact_match');
            }
        }

        $candidates = $this->hybridCandidates($normalized, $catalog, $limit ?? 8);
        $best = (float) ($candidates[0]['score'] ?? 0);
        $second = (float) ($candidates[1]['score'] ?? 0);

        if ($best < self::MIN_SCORE || ($best - $second) < self::MIN_MARGIN) {
            $id = str_contains($normalized, 'agua') ? 'clarify.water_type' : 'fallback.unknown';
            if ($id === 'clarify.water_type') {
                Cache::put($this->pendingKey($conversationId), $id, self::PENDING_SECONDS);
            }

            return $this->finish($catalog, $id, str_starts_with($id, 'clarify.') ? 'clarification' : 'fallback', $original, $normalized, $candidates, null, $best < self::MIN_SCORE ? 'low_score' : 'insufficient_margin');
        }

        $routingCandidates = $candidates;
        foreach (['clarify.water_type', 'fallback.unknown'] as $controlId) {
            $control = $catalog->active($controlId);
            $routingCandidates[] = ['id' => $control['id'], 'topic' => $control['topic'], 'description' => $control['canonical_question'], 'score' => 0.0];
        }
        $decision = $aiMode === 'disabled' ? ['action' => 'answer', 'answer_id' => $candidates[0]['id']] : $this->groqChatService->routeApprovedResponse($original, $routingCandidates);
        $id = $this->decisionId($decision);
        if ($id === null || $catalog->active($id) === null || ! in_array($id, array_column($routingCandidates, 'id'), true)) {
            return $this->finish($catalog, 'fallback.unknown', 'fallback', $original, $normalized, $candidates, $decision, 'invalid_or_inactive_id');
        }

        return $this->finish($catalog, $id, 'routed', $original, $normalized, $candidates, $decision, 'approved_id');
    }

    private function hybridCandidates(string $message, ApprovedResponseCatalog $catalog, int $limit): array
    {
        $tokens = array_values(array_filter(explode(' ', $message), static fn (string $token): bool => mb_strlen($token) > 2));
        $candidates = [];
        foreach ($catalog->all() as $record) {
            if (! $record['active'] || ! str_starts_with($record['id'], 'answer.')) {
                continue;
            }
            // Only the canonical question and aliases are indexed; approved answers never enter retrieval.
            $indexedText = $this->normalize($record['canonical_question'].' '.implode(' ', $record['aliases']));
            $lexical = count(array_filter($tokens, static fn (string $token): bool => str_contains($indexedText, $token))) / max(1, count($tokens));
            similar_text($message, $indexedText, $semanticPercent);
            $score = round(($lexical * .7) + (($semanticPercent / 100) * .3), 4);
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

    private function finish(ApprovedResponseCatalog $catalog, string $id, string $sourceType, string $original, string $normalized, array $candidates, ?array $groqDecision, string $reason): array
    {
        $record = $catalog->active($id) ?? $catalog->active('fallback.unknown');
        Log::info('Decisión del router RAG controlado.', compact('original', 'normalized', 'candidates', 'groqDecision', 'id', 'sourceType', 'reason'));

        return ['answer' => $record['approved_answer'], 'sources' => [['id' => $record['id'], 'topic' => $record['topic']]], 'fragments' => $candidates, 'source_type' => $sourceType, 'prompt' => ''];
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
