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
use Carbon\Carbon;

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
            return $this->sendError('Order Invitation not found');
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
            return $this->sendError('Order Invitation not found');
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
            return $this->sendError('Order Invitation not found');
        }

        $orderInvitation->delete();

        return $this->sendSuccess('Order Invitation deleted successfully');
    }

    public function myOrderInvitations(Request $request){

        $input = $request->all();

        if(!array_key_exists('driver_id', $input)){
            return $this->sendError('driver_id is required');
        }

        $orderInvitations = OrderInvitation::where([
            'driver_id' => $input['driver_id'],
            "is_waiting_acceptation" => true
        ])->get();

        return $this->sendResponse($orderInvitations->toArray(), 'Order Invitations retrieved successfully');

    }

    public function  accept($id, Request $request){
        /** @var OrderInvitation $orderInvitation */
        $orderInvitation = $this->orderInvitationRepository->find($id);

        if (empty($orderInvitation)) {
            return $this->sendError('Order Invitation not found', 400);
        }

        $order = Order::find($orderInvitation->order_id);
        if($order != null && $order->is_completed){

            if($orderInvitation->is_waiting_acceptation){
                $orderInvitation->is_waiting_acceptation = false;
                $orderInvitation->latitude = $request->input('latitude');
                $orderInvitation->longitude = $request->input('longitude');
                $orderInvitation->save();
            }

            return $this->sendError('Order already completed', 400);

        }

        if($orderInvitation->is_waiting_acceptation){
            $orderInvitation->is_waiting_acceptation = false;
            $orderInvitation->acceptation_time = now();
            $orderInvitation->latitude = $request->input('latitude');
            $orderInvitation->longitude = $request->input('longitude');
            $orderInvitation->save();

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
        /** @var OrderInvitation $orderInvitation */
        $orderInvitation = $this->taskInvitationRepository->find($id);

        if (empty($orderInvitation)) {
            return $this->sendError('Order Invitation not found');
        }

        if($orderInvitation->is_waiting_acceptation){
            $orderInvitation->is_waiting_acceptation = false;
            $orderInvitation->rejection_time = now();
            $orderInvitation->latitude = $request->input('latitude');
            $orderInvitation->longitude = $request->input('longitude');
            $orderInvitation->save();

            //TODO Vérifier s'il y a d'autre invitation ou relancer la recherche

            $orderInvitations = OrderInvitation::where([
                'user_id' => $orderInvitation->user_id,
                "is_waiting_acceptation" => true
            ])->get();

            return $this->sendResponse($orderInvitations->toArray(), 'Order Invitation retrieved successfully');
        }else{
            return $this->sendError('Affectation déjà traitée');
        }
    }
}
