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
use App\Models\OrderInvitation;
use App\Models\RoutePoint;
use App\Events\OrderAssigned;
use App\Utils\CustomerNotificationsUtils;
use App\Models\CustomerNotification;
use App\Events\CustomerNotificationCreated;




class TripService
{
    private const MAX_DRIVER_ATTEMPTS = 3;
    private const INVITATION_DELAY_SECONDS = 15;

    private OrderAssignmentV1Service $orderAssignmentService;

    public function __construct(OrderAssignmentV1Service $orderAssignmentService)
    {
        // Injection de dépendances possible ici (repositories, services, etc.)
        $this->orderAssignmentService = $orderAssignmentService;
    }

    /**
     * Crée une demande de course et génère les invitations aux chauffeurs.
     */
    public function createRequest(array $drivers, $carrier_id, $order_id): TripRequest
    {
        echo "Start createRequest";

        $tripRequest = TripRequest::create([
            'carrier_id' => $carrier_id,
            'order_id' => $order_id,
        ]);

        if (empty($drivers)) {
            $this->failTripRequest($tripRequest);
            return $tripRequest;
        }

        $this->createDriverInvitations($tripRequest, $drivers, $order_id);
        return $tripRequest;
    }

    /**
     * Envoie une invitation au chauffeur suivant dans la file.
     */
    public function dispatchNextDriverInvitation(TripRequest $tripRequest, int $retry = 0, int $orderIndex = 0): void
    {
        if ($orderIndex === 0) {
            $this->incrementAttemptNumber($tripRequest, $retry + 1);
        }

        $invitation = $this->getNextInvitation($tripRequest, $retry, $orderIndex);

        if (!$invitation) {
            $this->handleNoDriverFound($tripRequest, $retry);
            return;
        }

        $this->notifyDriver($tripRequest, $invitation, $retry, $orderIndex);
    }

    /**
     * Gestion des cas où aucun chauffeur ne peut être notifié.
     */
    public function handleNoDriverFound(TripRequest $tripRequest, int $retry): void
    {
        if ($retry < self::MAX_DRIVER_ATTEMPTS) {
            $this->dispatchNextDriverInvitation($tripRequest, $retry + 1);
        } else {
            $this->failTripRequest($tripRequest);
            $this->notifyNoDriverFound($tripRequest);
        }
    }

    /**
     * Notifie le client qu'aucun chauffeur n'est disponible.
     */
    public function notifyNoDriverFound(TripRequest $tripRequest): void
    {
        try {
            $title = "Aucun conducteur n'a pu vous trouver pour votre course #{$tripRequest->order_id}.";
            $subtitle = "Aucun conducteur n'est disponible pour l'instant, merci de ressayer plus tard.";

            $notification = CustomerNotification::create([
                'customer_id' => $tripRequest->order->customer_id,
                'title' => $title,
                'subtitle' => $subtitle,
                'data_id' => $tripRequest->order_id,
                'type' => $tripRequest->order->table,
                'is_read' => false,
                'is_received' => false,
                'meta_data' => null,
            ]);

            event(new CustomerNotificationCreated($notification));
        } catch (\Exception $e) {
            Log::error("Erreur de notification client (aucun chauffeur trouvé) : " . $e->getMessage());
        }
    }

    // ------------------------------
    // Méthodes privées d'aide
    // ------------------------------

    private function createDriverInvitations(TripRequest $tripRequest, array $drivers, $order_id): void
    {
        foreach ($drivers as $index => $driver_id) {
            OrderInvitation::create([
                'driver_id' => $driver_id,
                'order_id' => $order_id,
                'trip_request_id' => $tripRequest->id,
                'is_waiting_acceptation' => true,
                'acceptation_time' => null,
                'rejection_time' => null,
                'latitude' => null,
                'longitude' => null,
                'attempt_number' => 0,
                'index' => $index,
                'status' => TripDriverAttempt::PENDING,
            ]);
        }
    }

    private function incrementAttemptNumber(TripRequest $tripRequest, int $attemptNumber): void
    {
        $tripRequest->invitations()->update(['attempt_number' => $attemptNumber]);
    }

    private function getNextInvitation(TripRequest $tripRequest, int $retry, int $orderIndex): ?OrderInvitation
    {
        if ($retry === 0) {
            return $tripRequest->invitations()
                ->where('status', OrderInvitation::PENDING)
                ->orderBy('index', 'asc')
                ->first();
        }

        return OrderInvitation::where('trip_request_id', $tripRequest->id)
            ->where('attempt_number', $retry + 1)
            ->where('index', $orderIndex)
            ->first();
    }


    private function notifyDriver(TripRequest $tripRequest, OrderInvitation $invitation, int $retry, int $orderIndex): void
    {
        $invitation->update(['status' => OrderInvitation::NOTIFIED]);

        event(new OrderAssigned($invitation));

        AssignTimeoutCheck::dispatch($tripRequest, $invitation, $retry, $orderIndex)
            ->delay(now()->addSeconds(self::INVITATION_DELAY_SECONDS));

    }

    private function failTripRequest(TripRequest $tripRequest): void
    {
        $tripRequest->update(['status' => TripRequest::FAILED]);
    }


    public function getDriverAndNotify(Order $order, $limit = 5, $maxDistance = null)
    {
        $driversData = $this->orderAssignmentService->adaptedExpressOrderAssignment($order, $limit, $maxDistance);
        $drivers =  array_column($driversData, 'driver_id');
        $carrier_id = $driversData[0]['carrier_id'];

        $tripRequest = $this->createRequest($drivers, $carrier_id, $order->id);
        $this->dispatchNextDriverInvitation($tripRequest);
        return 0;
    }   
}
