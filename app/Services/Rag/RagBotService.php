<?php

namespace App\Services\Rag;

use App\Services\Bot\IntentDetectorService;

class RagBotService
{
    private const DEFAULT_TOP_K = 3;

    public function __construct(
        private readonly KnowledgeBaseService $knowledgeBaseService,
        private readonly IntentDetectorService $intentDetectorService,
    ) {
    }

    /**
     * @return array{answer:string,sources:array<int,string>,fragments:array<int,array{filename:string,chunk_index:int,content:string,score:float}>,source_type:string,prompt:string}
     */
    public function answer(string $question, ?int $limit = null): array
    {
        $intent = $this->intentDetectorService->detect($question);

        if ($intent !== null && $intent['use_rag'] === false && $intent['response'] !== null) {
            return [
                'answer' => $intent['response'],
                'sources' => ['respuesta automática'],
                'fragments' => [],
                'source_type' => 'intent',
                'prompt' => '',
            ];
        }

        $topK = $this->resolveTopK($limit);
        $fragments = $this->retrieveRelevantChunks($question, $topK);
        $ragAnswer = $this->generateFinalAnswer($question, $fragments);

        if ($intent !== null && $intent['prepend_response'] !== null) {
            $combinedAnswer = $intent['prepend_response'];

            if ($fragments !== []) {
                $combinedAnswer .= "\n\n".$ragAnswer['answer'];
            }

            return [
                'answer' => $combinedAnswer,
                'sources' => $fragments === [] ? ['respuesta automática'] : $ragAnswer['sources'],
                'fragments' => $fragments,
                'source_type' => $fragments === [] ? 'intent' : 'rag',
                'prompt' => $ragAnswer['prompt'],
            ];
        }

        return $ragAnswer;
    }

    /**
     * @return array<int,array{filename:string,chunk_index:int,content:string,score:float}>
     */
    private function retrieveRelevantChunks(string $question, int $topK): array
    {
        return $this->knowledgeBaseService->search($question, $topK);
    }

    /**
     * @param  array<int,array{filename:string,chunk_index:int,content:string,score:float}>  $fragments
     * @return array{answer:string,sources:array<int,string>,fragments:array<int,array{filename:string,chunk_index:int,content:string,score:float}>,source_type:string,prompt:string}
     */
    private function generateFinalAnswer(string $question, array $fragments): array
    {
        $prompt = $this->buildPrompt($question, $fragments);

        if ($fragments === []) {
            return [
                'answer' => 'No tengo el dato exacto con lo que tengo ahora. Si querés, te ayudo a reformular la consulta o a buscarlo por tema.',
                'sources' => [],
                'fragments' => [],
                'source_type' => 'rag',
                'prompt' => $prompt,
            ];
        }

        $answer = $this->synthesizeNaturalAnswer($fragments);

        $sources = array_values(array_unique(array_map(
            static fn (array $fragment): string => sprintf('%s#chunk-%d', $fragment['filename'], $fragment['chunk_index']),
            $fragments
        )));

        return [
            'answer' => $answer,
            'sources' => $sources,
            'fragments' => $fragments,
            'source_type' => 'rag',
            'prompt' => $prompt,
        ];
    }

    /**
     * @param  array<int,array{filename:string,chunk_index:int,content:string,score:float}>  $fragments
     */
    private function buildPrompt(string $question, array $fragments): string
    {
        $context = implode("\n\n", array_map(
            static fn (array $fragment): string => $fragment['content'],
            $fragments
        ));

        return "Sos un asistente del Refugio Agostino Rocca.\n"
            ."Instrucciones:\n"
            ."- Usá el contexto solo para responder, no lo copies literal.\n"
            ."- No menciones chunks, archivos ni base de conocimiento.\n"
            ."- Respondé en 2 a 5 frases, tono amable y natural (estilo WhatsApp).\n"
            ."- Usá listas breves solo si realmente ayudan.\n"
            ."- Si el contexto no alcanza, decí que no tenés el dato exacto.\n\n"
            ."Pregunta:\n{$question}\n\n"
            ."Contexto:\n{$context}";
    }

    /**
     * @param  array<int,array{filename:string,chunk_index:int,content:string,score:float}>  $fragments
     */
    private function synthesizeNaturalAnswer(array $fragments): string
    {
        $sentences = [];

        foreach ($fragments as $fragment) {
            $parts = preg_split('/(?<=[.!?])\s+/u', trim($fragment['content'])) ?: [];

            foreach ($parts as $part) {
                $clean = trim(preg_replace('/\s+/u', ' ', strip_tags($part)) ?? '');

                if ($clean === '' || mb_strlen($clean) < 45) {
                    continue;
                }

                if (preg_match('/^#{1,6}\s|^[-*]\s|^\d+[\.)]\s/u', $clean)) {
                    continue;
                }

                $sentences[] = $clean;

                if (count($sentences) >= 4) {
                    break 2;
                }
            }
        }

        if ($sentences === []) {
            return 'No tengo el dato exacto con lo que tengo ahora. Si querés, te ayudo a precisarlo un poco más.';
        }

        $selected = array_slice(array_values(array_unique($sentences)), 0, 4);
        $summary = implode(' ', $selected);

        return preg_replace('/\s+/u', ' ', $summary) ?? $summary;
    }

    private function resolveTopK(?int $limit): int
    {
        $configuredTopK = (int) env('RAG_TOP_K', self::DEFAULT_TOP_K);
        $defaultTopK = max(2, min(3, $configuredTopK));

        if ($limit === null) {
            return $defaultTopK;
        }

        return max(2, min(3, $limit));
    }
}
