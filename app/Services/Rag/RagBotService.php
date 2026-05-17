<?php

declare(strict_types=1);

namespace App\Services\Rag;

class RagBotService
{
    public function __construct(
        protected KnowledgeBaseService $knowledgeBase,
    ) {
    }

    /**
     * @return array{answer: string, sources: array<int, string>, matches: array<int, array{source: string, chunk_index: int, text: string, score: float}>}
     */
    public function answer(string $question, int $limit = 3): array
    {
        $matches = $this->knowledgeBase->search($question, $limit);

        if (empty($matches) || $matches[0]['score'] < 1.5) {
            return [
                'answer' => 'No encontré información suficiente en la base de conocimiento para responder eso.',
                'sources' => [],
                'matches' => $matches,
            ];
        }

        $sources = array_values(array_unique(array_map(fn (array $match): string => $match['source'], $matches)));
        $summarized = [];

        foreach ($matches as $match) {
            $text = trim(preg_replace('/\s+/u', ' ', $match['text']) ?? $match['text']);
            $summarized[] = mb_strlen($text) > 260 ? mb_substr($text, 0, 257).'...' : $text;
        }

        $answer = "Encontré información relevante en la base local:\n- ".implode("\n- ", $summarized);

        return [
            'answer' => $answer,
            'sources' => $sources,
            'matches' => $matches,
        ];
    }
}
