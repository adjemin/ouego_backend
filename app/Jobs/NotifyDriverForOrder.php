<?php

namespace App\Jobs;

use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\TripService;

class NotifyDriverForOrder
{
    use Dispatchable;

    public function __construct(
        public int $orderId,
        public ?int $limit,
        public ?float $maxDistance
    ) {}

    public function handle()
    {
        $tripService = app(TripService::class);
        $order = \App\Models\Order::find($this->orderId);
        
        if ($order) {
            $tripService->getDriverAndNotify($order, $this->limit, $this->maxDistance);
        }
    }
}