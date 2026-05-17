<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Rag\RagBotService;
use Illuminate\Console\Command;
use Throwable;

class MinimaikaChatCommand extends Command
{
    protected $signature = 'minimaika:chat';

    protected $description = 'Inicia un chat por terminal con Minimaika MS usando base de conocimiento local.';

    public function __construct(
        protected RagBotService $ragBot,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('🤖 Bienvenido a Minimaika MS (modo RAG local sin LLM).');
        $this->line('Escribí tu consulta. Para salir: salir, exit o quit.');

        while (true) {
            $question = trim((string) $this->ask('Tú'));

            if ($question === '') {
                $this->warn('Ingresá una pregunta válida.');
                continue;
            }

            if (in_array(mb_strtolower($question), ['salir', 'exit', 'quit'], true)) {
                $this->info('Hasta luego 👋');

                return self::SUCCESS;
            }

            try {
                $result = $this->ragBot->answer($question);

                $this->newLine();
                $this->line('Minimaika: '.$result['answer']);

                if (!empty($result['sources'])) {
                    $this->line('Fuentes: '.implode(', ', $result['sources']));
                }

                $this->newLine();
            } catch (Throwable $exception) {
                $this->error('Ocurrió un error procesando la consulta.');
                $this->line($exception->getMessage());
            }
        }
    }
}
