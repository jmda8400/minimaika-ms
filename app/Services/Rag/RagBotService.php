<?php

namespace App\Services\Rag;

use App\Services\Bot\IntentDetectorService;

class RagBotService
{
    public function __construct(
        private readonly KnowledgeBaseService $knowledgeBaseService,
        private readonly IntentDetectorService $intentDetectorService,
    ) {
    }

    /**
     * @return array{answer:string,sources:array<int,string>,fragments:array<int,array{filename:string,chunk_index:int,content:string,score:float}>,source_type:string}
     */
    public function answer(string $question, int $limit = 5): array
    {
        $intent = $this->intentDetectorService->detect($question);

        if ($intent !== null && $intent['use_rag'] === false && $intent['response'] !== null) {
            return [
                'answer' => $intent['response'],
                'sources' => ['respuesta automática'],
                'fragments' => [],
                'source_type' => 'intent',
            ];
        }

        $fragments = $this->knowledgeBaseService->search($question, $limit);
        $ragAnswer = $this->buildRagAnswer($fragments);

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
            ];
        }

        return $ragAnswer;
    }

    /**
     * @param  array<int,array{filename:string,chunk_index:int,content:string,score:float}>  $fragments
     * @return array{answer:string,sources:array<int,string>,fragments:array<int,array{filename:string,chunk_index:int,content:string,score:float}>,source_type:string}
     */
    private function buildRagAnswer(array $fragments): array
    {
        if ($fragments === []) {
            return [
                'answer' => 'No encontré información suficiente en la base de conocimiento para responder eso. Puedo ayudarte con reservas, ubicación, acceso, servicios, pagos, caminatas o preguntas frecuentes del refugio.',
                'sources' => [],
                'fragments' => [],
                'source_type' => 'rag',
            ];
        }

        $lines = [];

        foreach ($fragments as $fragment) {
            $lines[] = '- '.$fragment['content'];
        }

        $sources = array_values(array_unique(array_map(
            static fn (array $fragment): string => sprintf('%s#chunk-%d', $fragment['filename'], $fragment['chunk_index']),
            $fragments
        )));

        return [
            'answer' => "Encontré esta información en la base de conocimiento:\n\n".implode("\n", $lines),
            'sources' => $sources,
            'fragments' => $fragments,
            'source_type' => 'rag',
        ];
    }
}
