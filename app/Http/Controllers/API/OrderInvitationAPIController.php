<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\API\CreateOrderInvitationAPIRequest;
use App\Http\Requests\API\UpdateOrderInvitationAPIRequest;
use App\Models\OrderInvitation;
use App\Models\Order;
use App\Models\RoutePoint;
use App\Repositories\OrderInvitationRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\AppBaseController;
use Illuminate\Support\Collection;
use App\Models\CustomerNotification;
use App\Events\CustomerNotificationCreated;
use App\Services\DriverAssignmentService;
use Illuminate\Support\Facades\Log;

/**
 * Class OrderInvitationAPIController
 */
class OrderInvitationAPIController extends AppBaseController
{
    private OrderInvitationRepository $orderInvitationRepository;

    public function __construct(OrderInvitationRepository $orderInvitationRepo)
    {
        $this->orderInvitationRepository = $orderInvitationRepo;
    }

    /**
     * Display a listing of the OrderInvitations.
     * GET|HEAD /order-invitations
     */
    public function index(Request $request): JsonResponse
    {

        $driver = auth('api-drivers')->user();

        $orderInvitations = $this->orderInvitationRepository->allQuery(
            $request->except(['skip', 'limit']),
            $request->get('skip'),
            $request->get('limit')
        )->where('driver_id', $driver->id)
        ->where('is_waiting_acceptation', true)
        ->orderBy("created_at", 'desc')->get();

        return $this->sendResponse($orderInvitations->toArray(), 'Order Invitations retrieved successfully');
    }

    /**
     * Store a newly created OrderInvitation in storage.
     * POST /order-invitations
     */
    public function store(CreateOrderInvitationAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        $orderInvitation = $this->orderInvitationRepository->create($input);

        return $this->sendResponse($orderInvitation->toArray(), 'Order Invitation saved successfully');
    }

    /**
     * Display the specified OrderInvitation.
     * GET|HEAD /order-invitations/{id}
     */
    public function show($id): JsonResponse
    {
        /** @var OrderInvitation $orderInvitation */
        $orderInvitation = $this->orderInvitationRepository->find($id);

        if (empty($orderInvitation)) {
            return $this->sendError('Invitation introuvable');
        }

        return $this->sendResponse($orderInvitation->toArray(), 'Order Invitation retrieved successfully');
    }

    /**
     * Update the specified OrderInvitation in storage.
     * PUT/PATCH /order-invitations/{id}
     */
    public function update($id, UpdateOrderInvitationAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        /** @var OrderInvitation $orderInvitation */
        $orderInvitation = $this->orderInvitationRepository->find($id);

        if (empty($orderInvitation)) {
            return $this->sendError('Invitation introuvable');
        }

        $orderInvitation = $this->orderInvitationRepository->update($input, $id);

        return $this->sendResponse($orderInvitation->toArray(), 'OrderInvitation updated successfully');
    }

    /**
     * Remove the specified OrderInvitation from storage.
     * DELETE /order-invitations/{id}
     *
     * @throws \Exception
     */
    public function destroy($id): JsonResponse
    {
        /** @var OrderInvitation $orderInvitation */
        $orderInvitation = $this->orderInvitationRepository->find($id);

        if (empty($orderInvitation)) {
            return $this->sendError('Invitation introuvable');
        }

        $orderInvitation->delete();

        return $this->sendSuccess('Order Invitation deleted successfully');
    }

    public function myOrderInvitations(Request $request){

        $input = $request->all();

        if(!array_key_exists('driver_id', $input)){
            return $this->sendError('Le champ driver_id est obligatoire');
        }

        $orderInvitations = OrderInvitation::where([
            'driver_id' => $input['driver_id'],
            "is_waiting_acceptation" => true
        ])->get();

        return $this->sendResponse($orderInvitations->toArray(), 'Order Invitations retrieved successfully');

    }

    public function  accept($id, Request $request){

        $cdriver = auth('api-drivers')->user();
        /** @var OrderInvitation $orderInvitation */
        $orderInvitation = $this->orderInvitationRepository->find($id);

        // Un chauffeur ne peut accepter que ses propres invitations
        if (empty($orderInvitation) || !$this->belongsToDriver($orderInvitation, $cdriver)) {
            return $this->sendError('Invitation introuvable', 400);
        }

        $order = Order::find($orderInvitation->order_id);
        if($order != null && $order->is_completed){

            if($orderInvitation->is_waiting_acceptation){
                $orderInvitation->is_waiting_acceptation = false;
                $orderInvitation->latitude = $request->input('latitude');
                $orderInvitation->longitude = $request->input('longitude');
                $orderInvitation->save();
            }

            return $this->sendError('Commande déjà terminée', 400);

        }

        if($orderInvitation->is_waiting_acceptation){
            $orderInvitation->is_waiting_acceptation = false;
            $orderInvitation->acceptation_time = now();
            $orderInvitation->latitude = $request->input('latitude');
            $orderInvitation->longitude = $request->input('longitude');
            $orderInvitation->save();

            // TODO Vérifier s'il y a d'autres invitations et marquer comme rejeter
            OrderInvitation::where([
                "order_id" => $orderInvitation->order_id,
                "is_waiting_acceptation" => true
            ])
            ->update([
                "is_waiting_acceptation" => false,
                "rejection_time" => now()
            ]);

            /** @var Order $order */
            $order = $orderInvitation->getOrderAttribute();

            if($order != null){

                $order->update(
                    [
                        "driver_id" => $orderInvitation->driver_id,
                        "status" => Order::PERFORMER_FOUND,
                        "acceptation_time" => now()
                    ]
                );

                // Register order history
                $order->newOrderHistory(Order::PERFORMER_FOUND, $cdriver->table, $cdriver->id);

                //Send notification to customer
                $title = "Course #".$orderInvitation->order_id." a été attribuée";
                $subtitle = "Nous avons trouvé un conducteur pour la course";
                $userNotification = CustomerNotification::create([
                    'customer_id' => $order->customer_id,
                    'title' => $title,
                    'subtitle' => $subtitle,
                    'data_id' => $orderInvitation->order_id,
                    'type' => $order->table,
                    'is_read' => false,
                    'is_received' => false,
                    'meta_data' => null
                ]);

                // Déclenche l'événement
                event(new CustomerNotificationCreated($userNotification));
            }

            //Désactiver les autres invitations
            $others_task_invitations = OrderInvitation::where([
                'order_id' => $orderInvitation->order_id,
                "is_waiting_acceptation" => true
            ])->get();

            if($others_task_invitations != null){
                foreach ($others_task_invitations as $others_task_invitation){
                    $others_task_invitation->is_waiting_acceptation = false;
                    $others_task_invitation->save();
                }
            }


            /** @var Collection $route_points */
            $route_points = $order->route_points;
            foreach ($route_points as $route_point){
                $order = Order::where(['id' => $route_point->order_id])->first();
                if(!$order->is_completed){
                    $order->driver_id = $orderInvitation->driver_id;
                    $order->status = Order::PERFORMER_FOUND;
                    $order->acceptation_time = now();
                    $order->save();
                }


                if(!$route_point->is_completed){
                    $route_point->status = RoutePoint::WAITING;
                    $route_point->save();
                }


            }

            $orderInvitations = OrderInvitation::where([
                'driver_id' => $orderInvitation->driver_id,
                "is_waiting_acceptation" => true
            ])->get();


            return $this->sendResponse($orderInvitations->toArray(), 'Order Invitation retrieved successfully', 400);
        }else{
            return $this->sendError('Affectation déjà traitée', 400);
        }
    }

    public function  refuse($id, Request $request){
        $cdriver = auth('api-drivers')->user();
        /** @var OrderInvitation $orderInvitation */
        $orderInvitation = $this->orderInvitationRepository->find($id);

        // Un chauffeur ne peut refuser que ses propres invitations
        if (empty($orderInvitation) || !$this->belongsToDriver($orderInvitation, $cdriver)) {
            return $this->sendError('Invitation introuvable');
        }

        if($orderInvitation->is_waiting_acceptation){
            $orderInvitation->is_waiting_acceptation = false;
            $orderInvitation->rejection_time = now();
            $orderInvitation->latitude = $request->input('latitude');
            $orderInvitation->longitude = $request->input('longitude');
            $orderInvitation->save();

            $this->relaunchLookupIfNoPendingInvitation($orderInvitation->order_id);

            $orderInvitations = OrderInvitation::where([
                'driver_id' => $orderInvitation->driver_id,
                "is_waiting_acceptation" => true
            ])->get();

            return $this->sendResponse($orderInvitations->toArray(), 'Order Invitation retrieved successfully');
        }else{
            return $this->sendError('Affectation déjà traitée');
        }
    }

    /**
     * Une invitation d'un autre chauffeur est traitée comme introuvable, pour ne pas révéler son existence.
     */
    private function belongsToDriver(OrderInvitation $orderInvitation, $driver): bool
    {
        return $driver != null && (int) $orderInvitation->driver_id === (int) $driver->id;
    }

    /**
     * Relance la recherche de chauffeurs dès qu'un refus laisse la commande sans invitation en attente,
     * sans attendre le passage de la tâche automatique (toutes les 2 minutes).
     */
    private function relaunchLookupIfNoPendingInvitation(int $orderId): void
    {
        $order = Order::find($orderId);

        if ($order == null
            || $order->driver_id != null
            || $order->status != Order::PERFORMER_LOOKUP
            || $order->is_completed
            || $order->performerLookupDeadline()->isPast()) {
            return;
        }

        $hasPendingInvitation = OrderInvitation::where([
            'order_id' => $orderId,
            'is_waiting_acceptation' => true,
        ])->exists();

        if ($hasPendingInvitation) {
            return;
        }

        try {
            app(DriverAssignmentService::class)->sendInvitations($order, 10);
        } catch (\Throwable $e) {
            // Le refus est enregistré ; la tâche automatique reprendra la recherche
            Log::warning("OrderInvitationAPIController: relance de la recherche impossible pour la commande #{$orderId} : ".$e->getMessage());
        }
    }
}
