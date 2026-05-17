<?php

declare(strict_types=1);

namespace App\Services\Rag;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class KnowledgeBaseService
{
    /**
     * @var array<int, array{source: string, chunk_index: int, text: string, normalized: string, tokens: array<int, string>, token_frequency: array<string, int>}>
     */
    protected array $chunks = [];

    protected string $knowledgePath;

    public function __construct(
        protected Filesystem $filesystem,
    ) {
        $this->knowledgePath = storage_path('knowledge');
    }

    public function setKnowledgePath(string $path): void
    {
        $this->knowledgePath = $path;
        $this->chunks = [];
    }

    public function loadKnowledgeBase(): void
    {
        if (!empty($this->chunks)) {
            return;
        }

        if (!$this->filesystem->isDirectory($this->knowledgePath)) {
            return;
        }

        $files = collect($this->filesystem->files($this->knowledgePath))
            ->filter(fn (\SplFileInfo $file): bool => in_array($file->getExtension(), ['md', 'txt'], true))
            ->sortBy(fn (\SplFileInfo $file): string => $file->getFilename())
            ->values();

        foreach ($files as $file) {
            $content = trim($this->filesystem->get($file->getRealPath()));

            if ($content === '') {
                continue;
            }

            $chunkTexts = $this->chunkText($content);

            foreach ($chunkTexts as $chunkIndex => $chunkText) {
                $normalized = $this->normalizeText($chunkText);
                $tokens = $this->tokenize($normalized);

                if (empty($tokens)) {
                    continue;
                }

                $this->chunks[] = [
                    'source' => $file->getFilename(),
                    'chunk_index' => $chunkIndex,
                    'text' => $chunkText,
                    'normalized' => $normalized,
                    'tokens' => $tokens,
                    'token_frequency' => array_count_values($tokens),
                ];
            }
        }
    }

    /**
     * @return array<int, array{source: string, chunk_index: int, text: string, score: float}>
     */
    public function search(string $question, int $limit = 3): array
    {
        $this->loadKnowledgeBase();

        $questionTokens = $this->tokenize($this->normalizeText($question));

        if (empty($questionTokens) || empty($this->chunks)) {
            return [];
        }

        $questionFrequencies = array_count_values($questionTokens);
        $results = [];

        foreach ($this->chunks as $chunk) {
            $score = $this->calculateScore($questionFrequencies, $chunk['token_frequency']);

            if ($score <= 0) {
                continue;
            }

            $results[] = [
                'source' => $chunk['source'],
                'chunk_index' => $chunk['chunk_index'],
                'text' => $chunk['text'],
                'score' => round($score, 4),
            ];
        }

        usort($results, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($results, 0, $limit);
    }

    /**
     * @param array<string, int> $questionFrequencies
     * @param array<string, int> $chunkFrequencies
     */
    protected function calculateScore(array $questionFrequencies, array $chunkFrequencies): float
    {
        $score = 0.0;
        $matchedTerms = 0;

        foreach ($questionFrequencies as $token => $qFrequency) {
            if (!isset($chunkFrequencies[$token])) {
                continue;
            }

            $matchedTerms++;
            $score += min($qFrequency, $chunkFrequencies[$token]);
        }

        if ($matchedTerms === 0) {
            return 0.0;
        }

        // Bonifica cobertura de términos únicos de la pregunta.
        $coverage = $matchedTerms / max(1, count($questionFrequencies));

        return $score * (1 + $coverage);
    }

    /**
     * @return array<int, string>
     */
    protected function chunkText(string $text, int $maxChunkLength = 600): array
    {
        $paragraphs = preg_split('/\R{2,}/u', trim($text)) ?: [];
        $chunks = [];
        $buffer = '';

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                continue;
            }

            $candidate = $buffer === '' ? $paragraph : $buffer."\n\n".$paragraph;

            if (mb_strlen($candidate) <= $maxChunkLength) {
                $buffer = $candidate;
                continue;
            }

            if ($buffer !== '') {
                $chunks[] = $buffer;
            }

            if (mb_strlen($paragraph) <= $maxChunkLength) {
                $buffer = $paragraph;
                continue;
            }

            $sentences = preg_split('/(?<=[\.!\?])\s+/u', $paragraph) ?: [];
            $buffer = '';

            foreach ($sentences as $sentence) {
                $sentence = trim($sentence);
                if ($sentence === '') {
                    continue;
                }

                $sentenceCandidate = $buffer === '' ? $sentence : $buffer.' '.$sentence;

                if (mb_strlen($sentenceCandidate) <= $maxChunkLength) {
                    $buffer = $sentenceCandidate;
                    continue;
                }

                if ($buffer !== '') {
                    $chunks[] = $buffer;
                }

                $buffer = $sentence;
            }
        }

        if ($buffer !== '') {
            $chunks[] = $buffer;
        }

        return $chunks;
    }

    protected function normalizeText(string $text): string
    {
        $text = Str::ascii(mb_strtolower($text));
        $text = preg_replace('/[^a-z0-9\s]/u', ' ', $text) ?? '';

        return preg_replace('/\s+/u', ' ', trim($text)) ?? '';
    }

    /**
     * @return array<int, string>
     */
    protected function tokenize(string $normalizedText): array
    {
        $stopwords = [
            'el', 'la', 'los', 'las', 'de', 'del', 'y', 'o', 'u', 'en', 'a', 'al', 'un', 'una', 'unos', 'unas',
            'por', 'para', 'con', 'sin', 'que', 'como', 'donde', 'cuando', 'cual', 'cuales', 'es', 'son', 'se',
            'mi', 'tu', 'su', 'sus', 'lo', 'le', 'les', 'ya', 'si', 'no', 'me', 'te', 'nos', 'ustedes',
        ];

        $tokens = preg_split('/\s+/u', trim($normalizedText)) ?: [];

        return array_values(array_filter($tokens, fn (string $token): bool => $token !== '' && !in_array($token, $stopwords, true)));
    }
}
