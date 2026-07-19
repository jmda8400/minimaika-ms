<?php

namespace App\Services\Rag;

class KnowledgeBaseService
{
    private const DEFAULT_CHUNK_SIZE = 180;
    private const DEFAULT_CHUNK_OVERLAP = 40;
    private const MIN_SCORE = 0.2;

    /**
     * @var array<string, bool>
     */
    private array $stopWords;

    public function __construct(
        private readonly string $knowledgePath = 'storage/knowledge'
    ) {
        $this->stopWords = array_fill_keys($this->defaultStopWords(), true);
    }

    /**
     * @return array<int, array{filename:string, chunk_index:int, content:string, score:float,category:string,topic:string,location:?string}>
     */
    public function search(string $question, int $limit = 5): array
    {
        $questionTokens = $this->tokenize($question);

        if ($questionTokens === []) {
            return [];
        }

        $questionFreq = $this->tokenFrequencies($questionTokens);
        $questionUnique = array_keys($questionFreq);

        $matches = [];

        foreach ($this->loadChunks() as $chunk) {
            $chunkTokens = $this->tokenize($chunk['content']);

            if ($chunkTokens === []) {
                continue;
            }

            $chunkFreq = $this->tokenFrequencies($chunkTokens);
            $coverage = $this->computeCoverage($questionUnique, $chunkFreq);
            $frequency = $this->computeFrequencyScore($questionFreq, $chunkFreq);

            $score = ($coverage * 0.65) + ($frequency * 0.35);

            if ($score < self::MIN_SCORE) {
                continue;
            }

            $matches[] = [
                'filename' => $chunk['filename'],
                'chunk_index' => $chunk['chunk_index'],
                'content' => $chunk['content'],
                'score' => round($score, 4),
                'category' => $chunk['category'],
                'topic' => $chunk['topic'],
                'location' => $chunk['location'],
            ];
        }

        usort($matches, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($matches, 0, max(1, $limit));
    }

    /**
     * @return array<int, array{filename:string, chunk_index:int, content:string,category:string,topic:string,location:?string}>
     */
    private function loadChunks(): array
    {
        $basePath = base_path($this->knowledgePath);

        if (! is_dir($basePath)) {
            return [];
        }

        $chunks = [];
        $files = scandir($basePath) ?: [];

        foreach ($files as $file) {
            if (! preg_match('/\.(md|txt)$/i', $file)) {
                continue;
            }

            $fullPath = $basePath.DIRECTORY_SEPARATOR.$file;

            if (! is_file($fullPath)) {
                continue;
            }

            $content = trim((string) file_get_contents($fullPath));

            if ($content === '') {
                continue;
            }

            $metadata = $this->metadataFor($file, $content);
            $fileChunks = $this->splitInChunks($content);

            foreach ($fileChunks as $index => $chunkContent) {
                $chunks[] = [
                    'filename' => $file,
                    'chunk_index' => $index,
                    'content' => $chunkContent,
                    ...$metadata,
                ];
            }
        }

        return $chunks;
    }

    /**
     * @return array<int, string>
     */
    private function splitInChunks(string $content, int $chunkSize = self::DEFAULT_CHUNK_SIZE, int $overlap = self::DEFAULT_CHUNK_OVERLAP): array
    {
        // Preserve Markdown sections so a chunk has a coherent answer instead
        // of arbitrary words from two unrelated FAQ entries.
        $sections = preg_split('/(?=^#{1,2}\s)/mu', trim($content)) ?: [];
        $chunks = [];
        foreach ($sections as $section) {
            $words = preg_split('/\s+/u', trim($section)) ?: [];
            for ($start = 0; $start < count($words); $start += max(1, $chunkSize - $overlap)) {
                $chunks[] = implode(' ', array_slice($words, $start, $chunkSize));
                if ($start + $chunkSize >= count($words)) {
                    break;
                }
            }
        }

        return array_values(array_filter(array_map('trim', $chunks)));
    }

    /** @return array{category:string,topic:string,location:?string} */
    private function metadataFor(string $filename, string $content): array
    {
        preg_match('/^#\s+(.+)$/m', $content, $title);
        $topic = trim($title[1] ?? pathinfo($filename, PATHINFO_FILENAME));
        $category = preg_replace('/^\d+-/', '', pathinfo($filename, PATHINFO_FILENAME)) ?: 'general';
        $location = str_contains(mb_strtolower($content), 'pampa linda') ? 'Pampa Linda' : (str_contains(mb_strtolower($content), 'refugio') ? 'Refugio Agostino Rocca' : null);

        return compact('category', 'topic', 'location');
    }

    /**
     * @return array<int, string>
     */
    private function tokenize(string $text): array
    {
        $text = mb_strtolower($text);
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text) ?? '';

        $tokens = preg_split('/\s+/u', trim($text)) ?: [];

        return array_values(array_filter($tokens, function (string $token): bool {
            return $token !== '' && ! isset($this->stopWords[$token]) && mb_strlen($token) > 1;
        }));
    }

    /**
     * @param  array<int, string>  $tokens
     * @return array<string, int>
     */
    private function tokenFrequencies(array $tokens): array
    {
        $frequencies = [];

        foreach ($tokens as $token) {
            $frequencies[$token] = ($frequencies[$token] ?? 0) + 1;
        }

        return $frequencies;
    }

    /**
     * @param  array<int, string>  $questionUnique
     * @param  array<string, int>  $chunkFreq
     */
    private function computeCoverage(array $questionUnique, array $chunkFreq): float
    {
        $hits = 0;

        foreach ($questionUnique as $token) {
            if (isset($chunkFreq[$token])) {
                $hits++;
            }
        }

        return $hits / max(1, count($questionUnique));
    }

    /**
     * @param  array<string, int>  $questionFreq
     * @param  array<string, int>  $chunkFreq
     */
    private function computeFrequencyScore(array $questionFreq, array $chunkFreq): float
    {
        $matched = 0;
        $total = array_sum($questionFreq);

        foreach ($questionFreq as $token => $count) {
            if (! isset($chunkFreq[$token])) {
                continue;
            }

            $matched += min($count, $chunkFreq[$token]);
        }

        return $matched / max(1, $total);
    }

    /**
     * @return array<int, string>
     */
    private function defaultStopWords(): array
    {
        return [
            'de', 'la', 'el', 'que', 'para', 'con', 'una', 'unos', 'unas', 'un', 'y', 'o', 'en', 'a',
            'al', 'del', 'se', 'es', 'son', 'por', 'los', 'las', 'como', 'su', 'sus', 'lo', 'le', 'les',
            'mi', 'tu', 'ya', 'si', 'no', 'más', 'mas', 'sobre', 'entre', 'tambien', 'también', 'desde',
            'hasta', 'donde', 'dónde', 'cuando', 'cuándo', 'qué', 'que', 'cual', 'cuál', 'quien', 'quién',
        ];
    }
}
