<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Rag\KnowledgeBaseService;
use Illuminate\Console\Command;
use Throwable;

class MinimaikaKnowledgeTestCommand extends Command
{
    protected $signature = 'minimaika:knowledge-test {question : Pregunta para evaluar la recuperación} {--limit=5 : Cantidad máxima de fragmentos}';

    protected $description = 'Muestra fragmentos recuperados y score para depurar búsqueda en la base de conocimiento.';

    public function __construct(
        protected KnowledgeBaseService $knowledgeBase,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $question = (string) $this->argument('question');
        $limit = max(1, (int) $this->option('limit'));

        try {
            $matches = $this->knowledgeBase->search($question, $limit);

            if (empty($matches)) {
                $this->warn('No se recuperaron fragmentos para la pregunta dada.');

                return self::SUCCESS;
            }

            $this->info('Fragmentos recuperados:');

            foreach ($matches as $index => $match) {
                $preview = trim(preg_replace('/\s+/u', ' ', $match['text']) ?? $match['text']);
                $preview = mb_strlen($preview) > 220 ? mb_substr($preview, 0, 217).'...' : $preview;

                $this->line(sprintf(
                    "%d) [score=%.4f] %s#%d\n   %s",
                    $index + 1,
                    $match['score'],
                    $match['source'],
                    $match['chunk_index'],
                    $preview
                ));
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Error ejecutando knowledge-test.');
            $this->line($exception->getMessage());

            return self::FAILURE;
        }
    }
}
