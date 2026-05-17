<?php

namespace App\Console\Commands;

use App\Services\Rag\RagBotService;
use Illuminate\Console\Command;

class MinimaikaChatCommand extends Command
{
    protected $signature = 'minimaika:chat';

    protected $description = 'Chat local de Minimaika usando RAG sin modelos externos';

    public function __construct(private readonly RagBotService $ragBotService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Bienvenido a Minimaika. Escribí tu pregunta (salir/exit/quit para terminar).');

        while (true) {
            $question = trim((string) $this->ask('>'));

            if (in_array(mb_strtolower($question), ['salir', 'exit', 'quit'], true)) {
                $this->info('¡Hasta luego!');

                return self::SUCCESS;
            }

            if ($question === '') {
                $this->warn('Ingresá una pregunta válida.');

                continue;
            }

            $result = $this->ragBotService->answer($question);

            $this->line('');
            $this->info('Respuesta:');
            $this->line($result['answer']);

            $this->line('');
            $this->info('Fuentes usadas:');

            if ($result['sources'] === []) {
                $this->line('- (sin fuentes)');
            } else {
                foreach ($result['sources'] as $source) {
                    $this->line('- '.$source);
                }
            }

            $this->line('');
        }
    }
}
