<?php

namespace App\Jobs;

use App\Payments\PaymentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Envoie un remboursement à son opérateur. Les nouveaux essais ne passent pas par la file
 * mais par payments:reconcile (next_attempt_at), qui survit aux redémarrages du worker.
 */
class ProcessRefund implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public function __construct(public readonly int $refundId)
    {
    }

    public function handle(PaymentService $payments): void
    {
        $payments->processRefund($this->refundId);
    }
}
