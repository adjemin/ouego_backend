<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\API\CreateRoutePointAPIRequest;
use App\Http\Requests\API\UpdateRoutePointAPIRequest;
use App\Models\RoutePoint;
use App\Models\RoutePointHistory;
use App\Models\Order;
use App\Models\CustomerNotification;
use App\Events\CustomerNotificationCreated;
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


     public function indexByCustomer(Request $request): JsonResponse
    {
        $query = $request->get('q');
        $customer = auth('api-customers')->user();
       $routePoints = RoutePoint::where('customer_id', $customer->id)
            ->orderBy('address_name', 'desc')
            ->orderBy('latitude', 'desc')
            ->orderBy('longitude', 'desc')
            ->select(
                'id',
                'latitude',
                'longitude',
                'address_name',
                'type'
            )
            ->when(!empty($query), function ($searchQuery) use ($query) {
                return $searchQuery->where('address_name', 'ilike', "%$query%");
            })
            ->distinct(['address_name', 'latitude', 'longitude']) // Change this line
            ->limit(10)
            ->get();

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

        $driver = auth('api-drivers')->user();

        $input = $request->json()->all();
        $input_route_point = [];
        $input_route_point['status'] = $input['status'];


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
            RoutePoint::ARRIVED,
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

            $input_route_point['is_waiting'] = false;
            $input_route_point['is_successful'] = true;
            $input_route_point['is_completed'] = true;
            $input_route_point['completion_time'] = now();

            $title = $routePoint->title()." réussi(e)";

            RoutePointHistory::create([
                'route_point_id' => $routePoint->id,
                'latitude' => $request->input('latitude'),
                'longitude' => $request->input('longitude'),
                'status' => RoutePoint::SUCCESS
            ]);

            if($routePoint->type == 'destination'){

                $order = Order::where(['id' => $routePoint->order_id])->first();

                if($order != null ){

                    $order->update([
                        'status' => Order::DELIVERED_FINISH,
                        'is_running' => false,
                        'is_completed' => true,
                        'is_successful' => true,
                    ]);

                    // Register order history
                    $order->newOrderHistory(Order::DELIVERED_FINISH, $driver->table, $driver->id);
                }
            }

        }

        if($input['status'] == RoutePoint::FAILED){
            $input['is_waiting'] = false;
            $input['is_successful'] = false;
            $input['is_completed'] = true;
            $input['completion_time'] = now();

            $input_route_point['is_waiting'] = false;
            $input_route_point['is_successful'] = false;
            $input_route_point['is_completed'] = true;
            $input_route_point['completion_time'] = now();

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
            $input['is_started'] = true;
            $title = $routePoint->title()." démarré(e)";

            $input_route_point['is_waiting'] = false;
            $input_route_point['is_completed'] = false;
            $input_route_point['is_started'] = true;

            RoutePointHistory::create([
                'route_point_id' => $routePoint->id,
                'latitude' => $request->input('latitude'),
                'longitude' => $request->input('longitude'),
                'status' => RoutePoint::STARTED
            ]);

            if($routePoint->type == 'source'){

                $order = Order::where(['id' => $routePoint->order_id])->first();

                if($order != null){

                    $order->update([
                        'is_waiting' => false,
                        'is_running' => true,
                        'is_started' => true,
                        "start_time" => now(),
                        'is_completed' => false
                    ]);

                }

            }

             if($routePoint->type == 'destination'){

                $order = Order::where(['id' => $routePoint->order_id])->first();

                if($order != null){

                    $order->update([
                       'status' => Order::PICKUPED
                    ]);

                    // Register order history
                    $order->newOrderHistory(Order::PICKUPED, $driver->table, $driver->id);
                }

            }

        }

        if($input['status'] == RoutePoint::ARRIVED){
            $input['is_waiting'] = false;
            $input['is_completed'] = false;
            $input['is_arrived'] = true;

            $title = $routePoint->title()." arrivé(e)";

            $input_route_point['is_waiting'] = false;
            $input_route_point['is_completed'] = false;
            $input_route_point['is_arrived'] = true;

            RoutePointHistory::create([
                'route_point_id' => $routePoint->id,
                'latitude' => $request->input('latitude'),
                'longitude' => $request->input('longitude'),
                'status' => RoutePoint::ARRIVED
            ]);

            if($routePoint->type == 'source'){

                $order = Order::where(['id' => $routePoint->order_id])->first();

                if($order != null){

                    $order->update([
                        'status' => Order::PICKUP_ARRIVED,
                        'is_waiting' => false,
                        'is_running' => true,
                        'is_started' => true,
                        "start_time" => now(),
                        'is_completed' => false
                    ]);

                    // Register order history
                    $order->newOrderHistory(Order::PICKUP_ARRIVED, $driver->table, $driver->id);
                }
            }

            if($routePoint->type == 'destination'){

                $order = Order::where(['id' => $routePoint->order_id])->first();

                if($order != null){
                    $order->update([
                        'status' => Order::DELIVERED
                    ]);
                    // Register order history
                    $order->newOrderHistory(Order::DELIVERED, $driver->table, $driver->id);
                }
            }
        }

        if($input['status'] == RoutePoint::ACCEPTED){
            $input['is_waiting'] = false;
            $input['is_completed'] = false;

            $input_route_point['is_waiting'] = false;
            $input_route_point['is_completed'] = false;


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

            $input_route_point['is_waiting'] = false;
            $input_route_point['is_successful'] = false;
            $input_route_point['is_completed'] = true;
            $input_route_point['completion_time'] = now();

            RoutePointHistory::create([
                'route_point_id' => $routePoint->id,
                'latitude' => $request->input('latitude'),
                'longitude' => $request->input('longitude'),
                'status' => RoutePoint::CANCELLED
            ]);
        }

        $routePoint = $this->routePointRepository->update($input_route_point, $id);

        $order = Order::where(['id' => $routePoint->order_id])->first();

        if($routePoint->type == RoutePoint::POINT_TYPE_SOURCE && $input['status'] == RoutePoint::SUCCESS){

            $routePointsPickedListCount = RoutePoint::where([
                'order_id' => $routePoint->order_id,
                "is_completed" => true,
                "type" => RoutePoint::POINT_TYPE_SOURCE
            ])->count();

            $routePointsPickupListCount = RoutePoint::where([
                'order_id' => $routePoint->order_id,
                "type" => RoutePoint::POINT_TYPE_SOURCE
            ])->count();

            if($routePointsPickedListCount == $routePointsPickupListCount){
                $order->status = Order::PICKUPED;
                $order->is_waiting = false;
                $order->is_running = true;
                $order->is_completed = false;
                $order->save();

                // Register order history
                $order->newOrderHistory(Order::PICKUPED, $driver->table, $driver->id);

            }

        }


        if($routePoint->type == RoutePoint::POINT_TYPE_DESTINATION && $input['status'] == RoutePoint::SUCCESS){
            $routePointsDeliveriesCount = RoutePoint::where([
                'order_id' => $routePoint->order_id,
                "is_completed" => true,
                "type" => RoutePoint::POINT_TYPE_DESTINATION
            ])->count();

            $routePointsDeliveriesListCount = RoutePoint::where([
                'order_id' => $routePoint->order_id,
                "type" => RoutePoint::POINT_TYPE_DESTINATION
            ])->count();


            if($routePointsDeliveriesCount == $routePointsDeliveriesListCount){
                $order->status = Order::DELIVERED_FINISH;
                $order->is_running = false;
                $order->is_completed = true;
                $order->is_successful= true;
                $order->completion_time = now();
                $order->save();
                $order->chargeDriver();

                // Register order history
                $order->newOrderHistory(Order::DELIVERED_FINISH, $driver->table, $driver->id); 

            }
        }


        //Send Push notification to customer
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

        // Déclenche l'événement
        event(new CustomerNotificationCreated($userNotification));

        $order = Order::find($routePoint->order_id);

        return $this->sendResponse($order->toArray(), 'RoutePoint updated successfully');


    }
}
