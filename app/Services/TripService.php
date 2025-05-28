<?php 
namespace App\Services;

use App\Models\Carrier;
use App\Models\TripRequest;
use App\Models\TripDriverAttempt;
use App\Notifications\RideRequestNotification;
use App\Jobs\AssignTimeoutCheck;
use App\Models\Order;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Log;
use App\Notifications\NoDriverFoundNotification;
use App\Models\Driver;

class TripService
{

    private $attemptLimit = 2;

    public function __construct()
    {
        // Initialisation si nécessaire
    }
    /**
     * Crée une nouvelle demande de course (TripRequest) et assigne les
     * chauffeurs correspondants.
     *
     * @param Driver[] $drivers les chauffeurs à assigner
     * @param Carrier $carrier la compagnie de transport
     * @param Order $order la commande associée
     * @return TripRequest la demande de course créée
     */
    public function createRequest(Driver $drivers, Carrier $carrier, Order $order): TripRequest
    {
        // On crée une nouvelle demande de course
        $tripRequest = TripRequest::create(['carrier_id' => $carrier->id, 'order_id' => $order->id]);

        
        // Si aucun chauffeur n'est trouvé, on met à jour le statut de la demande
        if (empty($drivers)) {
            $tripRequest->update(['status' => TripRequest::FAILED]);
            return $tripRequest;
        }

        // On crée une nouvelle liste de tentatives de chauffeur
        foreach ($drivers as $index => $driver) {
            TripDriverAttempt::create([
                'trip_request_id' => $tripRequest->id,
                'driver_id' => $driver->id,
                'attempt_number' => 0,
                'order_index' => $index,
                'status' => TripDriverAttempt::PENDING,
            ]);
        }

        return $tripRequest;
    }

    public function notifyNextDriver(TripRequest $tripRequest, int $retry=0, int $orderIndex=0)
    {
        // Met à jour le numéro de tentative si on recommence un cycle
        if ($orderIndex === 0) {
            $tripRequest->attempts()->update(['attempt_number' => $retry + 1]);
        }

        // Récupère la tentative correspondante
        $attempt = $retry === 0
            ? $tripRequest->attempts()
                ->where('status', TripRequest::PENDING)
                ->orderBy('order_index', 'asc')
                ->first()
            : TripDriverAttempt::where('trip_request_id', $tripRequest->id)
                ->where('attempt_number', $retry + 1)
                ->where('order_index', $orderIndex)
                ->first();

        // Gère le cas où aucune tentative n’est trouvée
        if (!$attempt) {
            $this->handleNoDriverFound($tripRequest, $retry);
            return;
        }
        
        $attempt->update(['status' => 'notified']);
        Notification::sendNow($attempt->driver, new RideRequestNotification($tripRequest));

        AssignTimeoutCheck::dispatch($tripRequest, $attempt, $retry, $orderIndex)->delay(now()->addSeconds(15));
    }

    public function handleNoDriverFound(TripRequest $tripRequest, int $retry)
    {
        if ($retry < $this->attemptLimit) {
            // Relancer soit avec la même liste, soit une nouvelle recherche
            $this->notifyNextDriver($tripRequest, $retry + 1);
        } else {
            $tripRequest->update(['status' => 'failed']);
            $this->notifyNoDriverFound($tripRequest);
        }
    }

    public function notifyNoDriverFound(TripRequest $tripRequest)
    {
        try {
            // Notify the client that no driver was found
            Notification::send($tripRequest->order->customer, new NoDriverFoundNotification($tripRequest));
        } catch (\Exception $e) {
            // Log the error or handle it as needed
            Log::error("Failed to notify client about no driver found: " . $e->getMessage());
        }
    }

}
