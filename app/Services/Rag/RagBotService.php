<?php

namespace App\Services\Rag;

class RagBotService
{
    public function __construct(private readonly KnowledgeBaseService $knowledgeBaseService)
    {
    }

    /**
     * @return array{answer:string,sources:array<int,string>,fragments:array<int,array{filename:string,chunk_index:int,content:string,score:float}>}
     */
    public function answer(string $question, int $limit = 5): array
    {
        $fragments = $this->knowledgeBaseService->search($question, $limit);

        if ($fragments === []) {
            return [
                'answer' => 'No encontré información suficiente en la base de conocimiento para responder eso.',
                'sources' => [],
                'fragments' => [],
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

        $answer = "Encontré esta información en la base de conocimiento:\n\n".implode("\n", $lines);

        return [
            'answer' => $answer,
            'sources' => $sources,
            'fragments' => $fragments,
        ];
    }
}
