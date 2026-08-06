<?php

namespace App\Console\Commands;

use App\Services\Rag\RagBotService;
use Illuminate\Console\Command;

class MinimaikaChatCommand extends Command
{
    protected $signature = 'minimaika:chat {--debug : Mostrar diagnóstico resumido} {--show-catalog : Mostrar el catálogo compacto completo (requiere --debug)}';

    protected $description = 'Chat local de Minimaika usando RAG sin modelos externos';

    public function __construct(private readonly RagBotService $ragBotService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $showSources = filter_var(env('RAG_SHOW_SOURCES', false), FILTER_VALIDATE_BOOL);

        $this->info('Asistente virtual del Refugio Agostino Rocca. Escribí tu pregunta (salir/exit/quit para terminar).');

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

            if ($this->option('debug')) {
                $debug = $result['debug'] ?? [];
                $this->newLine();
                $this->info('Diagnóstico de clasificación:');
                $this->line('Mensaje: '.($debug['user_message'] ?? $question));
                $this->line('Catálogo de intenciones: '.count($debug['intent_ids_sent_to_groq'] ?? []));
                $this->line('Caracteres del catálogo: '.($debug['catalog_characters'] ?? 0));
                if ($this->option('show-catalog')) {
                    $this->line('Catálogo enviado: '.json_encode($debug['catalog_sent_to_groq'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                }
                $this->line('Respuesta cruda de Groq: '.($debug['groq_raw_response'] ?? '(sin respuesta)'));
                $this->line('Intención elegida: '.($debug['parsed_intent_id'] ?? 'unknown'));
                $this->line('Confianza: '.($debug['confidence'] ?? 'n/a'));
                $this->line('Groq status: '.($debug['groq_status'] ?? 'n/a'));
                $this->line('Failure reason: '.($debug['fallback_reason'] ?? 'none'));
                if (($debug['retry_after'] ?? null) !== null) {
                    $this->line('Retry after: '.$debug['retry_after'].' seconds');
                }
                $this->line('Prompt tokens: '.($debug['prompt_tokens'] ?? 'n/a'));
                $this->line('Completion tokens: '.($debug['completion_tokens'] ?? 'n/a'));
                $this->line('Total tokens: '.($debug['total_tokens'] ?? 'n/a'));
                $this->line('Modelo: '.($debug['model'] ?? 'n/a'));
                $this->line('Tiempo de respuesta: '.($debug['response_time_ms'] ?? 'n/a').' ms');
                $this->line('Resultado: '.($debug['validation_result'] ?? 'n/a'));
                $this->line('Respuesta oficial seleccionada: '.($debug['selected_answer_id'] ?? 'fallback'));
            }

            if ($showSources) {
                $this->line('');
                $this->info('Fuentes usadas (debug):');

                if ($result['sources'] === []) {
                    $this->line('- (sin fuentes)');
                } else {
                    foreach ($result['sources'] as $source) {
                        $this->line('- '.$source);
                    }
                }
            }

            $this->line('');
        }
    }
}
