<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\API\CreateRoutePointAPIRequest;
use App\Http\Requests\API\UpdateRoutePointAPIRequest;
use App\Models\RoutePoint;
use App\Models\RoutePointHistory;
use App\Models\Order;
use App\Models\CustomerNotification;
use App\Utilities\CustomerNotificationsUtils;
use App\Repositories\RoutePointRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\AppBaseController;

/**
 * Class RoutePointAPIController
 */
class RoutePointAPIController extends AppBaseController
{
    private RoutePointRepository $routePointRepository;

    public function __construct(RoutePointRepository $routePointRepo)
    {
        $this->routePointRepository = $routePointRepo;
    }

    /**
     * Display a listing of the RoutePoints.
     * GET|HEAD /route-points
     */
    public function index(Request $request): JsonResponse
    {
        $routePoints = $this->routePointRepository->all(
            $request->except(['skip', 'limit']),
            $request->get('skip'),
            $request->get('limit')
        );

        return $this->sendResponse($routePoints->toArray(), 'Route Points retrieved successfully');
    }

    /**
     * Store a newly created RoutePoint in storage.
     * POST /route-points
     */
    public function store(CreateRoutePointAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        $routePoint = $this->routePointRepository->create($input);

        return $this->sendResponse($routePoint->toArray(), 'Route Point saved successfully');
    }

    /**
     * Display the specified RoutePoint.
     * GET|HEAD /route-points/{id}
     */
    public function show($id): JsonResponse
    {
        /** @var RoutePoint $routePoint */
        $routePoint = $this->routePointRepository->find($id);

        if (empty($routePoint)) {
            return $this->sendError('Route Point not found');
        }

        return $this->sendResponse($routePoint->toArray(), 'Route Point retrieved successfully');
    }

    /**
     * Update the specified RoutePoint in storage.
     * PUT/PATCH /route-points/{id}
     */
    public function update($id, UpdateRoutePointAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        /** @var RoutePoint $routePoint */
        $routePoint = $this->routePointRepository->find($id);

        if (empty($routePoint)) {
            return $this->sendError('Route Point not found');
        }

        $routePoint = $this->routePointRepository->update($input, $id);

        return $this->sendResponse($routePoint->toArray(), 'RoutePoint updated successfully');
    }

    /**
     * Remove the specified RoutePoint from storage.
     * DELETE /route-points/{id}
     *
     * @throws \Exception
     */
    public function destroy($id): JsonResponse
    {
        /** @var RoutePoint $routePoint */
        $routePoint = $this->routePointRepository->find($id);

        if (empty($routePoint)) {
            return $this->sendError('Route Point not found');
        }

        $routePoint->delete();

        return $this->sendSuccess('Route Point deleted successfully');
    }

    /**
     * Update the specified RoutePoint in storage.
     * PUT/PATCH /route_points/{id}/update_status
     */
    public function updateStatus($id, Request $request){

        $input = $request->json()->all();

        /** @var RoutePoint $routePoint */
        $routePoint = $this->routePointRepository->find($id);

        if (empty($routePoint)) {
            return $this->sendError('Route Point not found', 400);
        }

        if (!array_key_exists('status', $input) || empty($input['status'])) {
            return $this->sendError('status is required', 400);
        }

        if(!in_array($input['status'], [
            RoutePoint::ACCEPTED,
            RoutePoint::FAILED,
            RoutePoint::STARTED,
            RoutePoint::CANCELLED,
            RoutePoint::WAITING,
            RoutePoint::SUCCESS,
        ])){
            return $this->sendError('status is invalid', 400);
        }

        $title = "Course #".$routePoint->order_id." a changé de statut";

        if($input['status'] == RoutePoint::SUCCESS){
            $input['is_waiting'] = false;
            $input['is_successful'] = true;
            $input['is_completed'] = true;
            $input['completion_time'] = now();

            $title = $routePoint->title()." réussi(e)";

            RoutePointHistory::create([
                'route_point_id' => $routePoint->id,
                'latitude' => $request->input('latitude'),
                'longitude' => $request->input('longitude'),
                'status' => RoutePoint::SUCCESS
            ]);

        }

        if($input['status'] == RoutePoint::FAILED){
            $input['is_waiting'] = false;
            $input['is_successful'] = false;
            $input['is_completed'] = true;
            $input['completion_time'] = now();
            $title = $routePoint->title()." échoué(e)";

            RoutePointHistory::create([
                'route_point_id' => $routePoint->id,
                'latitude' => $request->input('latitude'),
                'longitude' => $request->input('longitude'),
                'status' => RoutePoint::FAILED
            ]);
        }

        if($input['status'] == RoutePoint::STARTED){
            $input['is_waiting'] = false;
            $input['is_completed'] = false;
            $title = $routePoint->title()." démarré(e)";

            RoutePointHistory::create([
                'route_point_id' => $routePoint->id,
                'latitude' => $request->input('latitude'),
                'longitude' => $request->input('longitude'),
                'status' => RoutePoint::STARTED
            ]);

            if($routePoint->type == 'source'){

                $order = Order::where(['id' => $routePoint->order_id])->first();

                if($order != null && !$order->is_started){

                    $order->update([
                        'is_running' => true,
                        'is_started' => true,
                        "start_time" => now(),
                        'is_completed' => false
                    ]);

                }

            }
        }

        if($input['status'] == RoutePoint::ACCEPTED){
            $input['is_waiting'] = false;
            $input['is_completed'] = false;
            RoutePointHistory::create([
                'route_point_id' => $routePoint->id,
                'latitude' => $request->input('latitude'),
                'longitude' => $request->input('longitude'),
                'status' => RoutePoint::ACCEPTED
            ]);
        }

        if($input['status'] == RoutePoint::CANCELLED){
            $input['is_waiting'] = false;
            $input['is_successful'] = false;
            $input['is_completed'] = true;
            $input['completion_time'] = now();
            $title = $routePoint->title()." annulé(e)";

            RoutePointHistory::create([
                'route_point_id' => $routePoint->id,
                'latitude' => $request->input('latitude'),
                'longitude' => $request->input('longitude'),
                'status' => RoutePoint::CANCELLED
            ]);
        }

        $order = Order::where(['id' => $routePoint->order_id])->first();

        if($routePoint->type == "source" && $input['status'] == RoutePoint::SUCCESS){

            $routePointsPickedListCount = RoutePoint::where([
                'order_id' => $routePoint->order_id,
                "is_completed" => true,
                "type" => "source"
            ])->count();

            $routePointsPickupListCount = RoutePoint::where([
                'order_id' => $routePoint->order_id,
                "type" => "source"
            ])->count();

            if($routePointsPickedListCount == $routePointsPickupListCount){
                $order->status = Order::PICKUPED;
                $order->is_started = true;
                $order->is_running = true;
                $order->is_completed = false;
                $order->start_time = now();
                $order->save();

            }

        }


        if($routePoint->type == "destination" && $input['status'] == RoutePoint::SUCCESS){
            $routePointsDeliveriesCount = RoutePoint::where([
                'order_id' => $routePoint->order_id,
                "is_completed" => true,
                "type" => "destination"
            ])->count();

            $routePointsDeliveriesListCount = RoutePoint::where([
                'order_id' => $routePoint->order_id,
                "type" => "destination"
            ])->count();

            if($routePointsDeliveriesCount == $routePointsDeliveriesListCount){
                $order->status = Order::DELIVERED;
                $order->is_running = false;
                $order->is_completed = true;
                $order->is_successful= true;
                $order->completion_time = now();
                $order->save();
                $order->chargeDriver();

            }
        }


        //Send Push notification to user
        $subtitle = "Course #".$routePoint->order_id." a été mise à jour";
        $userNotification = CustomerNotification::create([
            'customer_id' => $order->customer_id,
            'title' => $title,
            'subtitle' => $subtitle,
            'data_id' => $routePoint->order_id,
            'type' => $order->table,
            'is_read' => false,
            'is_received' => false,
            'meta_data' => null
        ]);
        CustomerNotificationsUtils::notify($userNotification);

        $order = Order::find($routePoint->order_id);

        return $this->sendResponse($order->toArray(), 'RoutePoint updated successfully');


    }
}
