<?php

namespace App\Console\Commands;

use App\Services\Rag\KnowledgeBaseService;
use Illuminate\Console\Command;

class MinimaikaKnowledgeTestCommand extends Command
{
    protected $signature = 'minimaika:knowledge-test {question} {--limit=5}';

    protected $description = 'Prueba la recuperación de fragmentos de la base de conocimiento local';

    public function __construct(private readonly KnowledgeBaseService $knowledgeBaseService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $question = (string) $this->argument('question');
        $limit = max(1, (int) $this->option('limit'));

        $fragments = $this->knowledgeBaseService->search($question, $limit);

        if ($fragments === []) {
            $this->warn('No se recuperaron fragmentos para la pregunta dada.');

            return self::SUCCESS;
        }

        $this->info('Fragmentos recuperados:');

        foreach ($fragments as $index => $fragment) {
            $num = $index + 1;
            $this->line(sprintf(
                '%d) [score=%.4f] %s#chunk-%d',
                $num,
                $fragment['score'],
                $fragment['filename'],
                $fragment['chunk_index']
            ));
            $this->line($fragment['content']);
            $this->newLine();
        }

        return self::SUCCESS;
    }
}
