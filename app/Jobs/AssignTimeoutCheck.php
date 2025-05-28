<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\TripRequest;
use App\Models\Driver;
use App\Models\TripDriverAttempt;
use App\Services\TripService;
use App\Services\CarrierService;

class AssignTimeoutCheck implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public TripRequest $tripRequest, public Driver $driver, public int $retry, public int $orderIndex) {}

    public function handle()
    {
        $attempt = TripDriverAttempt::where('trip_request_id', $this->tripRequest->id)
            ->where('driver_id', $this->driver->id)
            ->latest()->first();

        if ($attempt->status === TripDriverAttempt::NOTIFIED || $attempt->status === TripDriverAttempt::REJECTED) {
            $attempt->status === TripDriverAttempt::NOTIFIED ? $attempt->update(['status' => TripDriverAttempt::TIMEOUT]): null;
            app(TripService::class)->notifyNextDriver($this->tripRequest, $this->retry, $this->orderIndex + 1);
        }
    }
}
