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

    private DriverAssignmentService $orderAssignmentService;

    public function __construct(DriverAssignmentService $orderAssignmentService)
    {
        // Injection de dépendances possible ici (repositories, services, etc.)
        $this->orderAssignmentService = $orderAssignmentService;
    }

    /**
     * Crée une demande de course et génère les invitations aux chauffeurs.
     */
    public function createRequest(array $drivers, $carrier_id, $order_id): TripRequest
    {

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


    /**
     * Recherche des chauffeurs et lance le processus de notification séquentielle
     * 
     * @param Order $order
     * @param int $limit Nombre maximum de chauffeurs à rechercher (défaut: 5)
     * @param float|null $maxDistance Distance maximum en mètres (optionnel)
     * @return array Résultat de l'opération avec statut et données
     */
    public function getDriverAndNotify(Order $order, $limit = 5, $maxDistance = null): array
    {
        try {

            // Validation des données d'entrée
            if (!$order || !$order->exists) {
                Log::error("getDriverAndNotify: Commande invalide", ['order_id' => $order?->id]);
                return [
                    'success' => false,
                    'message' => 'Commande invalide',
                    'data' => null
                ];
            }


            Log::info("Début de recherche de chauffeurs", [
                'order_id' => $order->id,
                'limit' => $limit,
                'max_distance' => $maxDistance
            ]);

            // Vérification du produit associé
            $product = $order->items->first();
            if (!$product || !$product->carrier_id) {
                Log::error("getDriverAndNotify: Aucun produit ou carrier_id manquant", [
                    'order_id' => $order->id,
                    'product_exists' => !!$product
                ]);
                return [
                    'success' => false,
                    'message' => 'Produit ou transporteur non trouvé pour cette commande',
                    'data' => null
                ];
            }

            // Recherche des chauffeurs avec l'algorithme de pondération
            $driversData = $this->orderAssignmentService->aggregatExpressOrderAssignment(
                $product->carrier_id, 
                $order->id, 
                $order->service_slug, 
                $limit, 
                $maxDistance
            );

    
            // Vérification que des chauffeurs ont été trouvés
            if (empty($driversData)) {
                Log::warning("getDriverAndNotify: Aucun chauffeur trouvé", [
                    'order_id' => $order->id,
                    'carrier_id' => $product->carrier_id,
                    'service_slug' => $order->service_slug
                ]);
                return [
                    'success' => false,
                    'message' => 'Aucun chauffeur disponible trouvé',
                    'data' => [
                        'drivers_searched' => 0,
                        'carrier_id' => $product->carrier_id
                    ]
                ];
            }

            $drivers = array_column($driversData, 'driver_id');
            $carrier_id =$product->carrier_id;

            Log::info("Chauffeurs trouvés, création de la demande de course", [
                'order_id' => $order->id,
                'drivers_count' => count($drivers),
                'carrier_id' => $carrier_id
            ]);

            
            // Création de la demande de course et des invitations
            $tripRequest = $this->createRequest($drivers, $carrier_id, $order->id);
            
            // Lancement du processus de notification séquentielle
            $this->dispatchNextDriverInvitation($tripRequest);

            Log::info("Processus de notification lancé avec succès", [
                'trip_request_id' => $tripRequest->id,
                'order_id' => $order->id,
                'drivers_invited' => count($drivers)
            ]);

            return [
                'success' => true,
                'message' => 'Processus de recherche de chauffeur lancé avec succès',
                'data' => [
                    'trip_request_id' => $tripRequest->id,
                    'drivers_invited' => count($drivers),
                    'carrier_id' => $carrier_id,
                    'drivers_data' => $driversData
                ]
            ];

        } catch (\Exception $e) {
            Log::error("Erreur dans getDriverAndNotify", [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Erreur lors de la recherche de chauffeurs: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }   
}
