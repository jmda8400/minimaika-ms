<?php

namespace App\Jobs;

use App\Services\WhatsApp\WhatsAppNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendWhatsAppClaimAlert implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var array<int,int> */
    public array $backoff = [30, 120, 300, 900];

    public function __construct(
        public readonly string $selection,
        public readonly string $customerPhone,
    ) {
    }

    public function handle(WhatsAppNotificationService $notifications): void
    {
        $notifications->sendClaim($this->selection, $this->customerPhone);
    }
}
