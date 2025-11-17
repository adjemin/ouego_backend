<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\TripRequest;
use App\Models\OrderInvitation;
use App\Models\TripDriverAttempt;
use App\Services\TripService;

class AssignTimeoutCheck implements ShouldQueue
// class AssignTimeoutCheck 
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 30;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public TripRequest $tripRequest, 
        public OrderInvitation $invitation, 
        public int $retry, 
        public int $orderIndex
    ) {}

    public function handle()
    {
      
        if ($this->invitation->status === OrderInvitation::NOTIFIED || $this->invitation->status === OrderInvitation::REJECTED) {
            $this->invitation->status === TripDriverAttempt::NOTIFIED ? $this->invitation->update(['status' => OrderInvitation::TIMEOUT]): null;
            app(TripService::class)->dispatchNextDriverInvitation($this->tripRequest, $this->retry, $this->orderIndex + 1);
        }
    }
}
