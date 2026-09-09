<?php

namespace App\Console\Commands;

use App\Services\WhatsApp\WhatsAppNotificationService;
use Illuminate\Console\Command;
use Throwable;

class SendWhatsAppHealthStatus extends Command
{
    protected $signature = 'whatsapp:send-health-status {--force : Ignorar horario y deduplicación}';

    protected $description = 'Envía al grupo configurado el estado de la sesión de WhatsApp';

    public function handle(WhatsAppNotificationService $notifications): int
    {
        try {
            if (! $notifications->sendHeartbeat((bool) $this->option('force'))) {
                $this->info('No correspondía enviar el mensaje o la sesión no está conectada.');

                return self::SUCCESS;
            }

            $this->info('Estado enviado al grupo configurado.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('No se pudo enviar el estado: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
