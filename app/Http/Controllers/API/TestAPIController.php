<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\AppBaseController;
use Illuminate\Http\Request;
use App\Services\OrderAssignmentV1Service;
use App\Services\OrderAssignmentV2Service;
use App\Services\TripService;
use App\Models\Order;
use App\Jobs\NotifyDriverForOrder;
use Illuminate\Support\Facades\Log;



class TestAPIController extends AppBaseController
{

    private $orderAssignmentService;
    private $orderAssignmentV2Service;
    private $tripService;

    public function __construct(
        OrderAssignmentV1Service $orderAssignmentService, 
        OrderAssignmentV2Service $orderAssignmentV2Service,
        TripService $tripService
    )
    {
        $this->orderAssignmentService = $orderAssignmentService;
        $this->orderAssignmentV2Service = $orderAssignmentV2Service;
        $this->tripService = $tripService;
        
    }

    public function searchNearDriverByCarrier(Request $request){
        try {
            $longitude = $request->input('longitude');
            $latitude = $request->input('latitude');
            if(empty($longitude) || empty($latitude)){
                return $this->sendError('Longitude and Latitude are required', 400);
            }
            if(!is_numeric($longitude) || !is_numeric($latitude)){
                return $this->sendError('Longitude and Latitude must be numeric', 400);
            }

            $nearDrivers = OrderAssignmentV2Service::searchNearDriverByCarrier($longitude, $latitude);

            return $this->sendResponse($nearDrivers, 'New Carrier found successfully');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }

    public function getNearestCarrierAndDrivers(Request $request){
        // try{
            $latitude = $request->input('latitude');
            $longitude = $request->input('longitude');
            if(empty($latitude) || empty($longitude)){
                return $this->sendError('Latitude and Longitude are required', 400);
            }
            if(!is_numeric($latitude) || !is_numeric($longitude)){
                return $this->sendError('Latitude and Longitude must be numeric', 400);
            }

            $rayonKm = $request->input('rayon_km');
            if(empty($rayonKm)){
                $rayonKm = 5;
            }

            $orderAssignmentService = new OrderAssignmentV1Service();
            $nearestCarriersAndDrivers = $orderAssignmentService->expressOrderAssignment($latitude, $longitude, $rayonKm);

            return $this->sendResponse($nearestCarriersAndDrivers, 'Get Nearest Carrier and Drivers found successfully');
        // }catch (\Exception $e){
        //     return $this->sendError($e->getMessage(), 400);
        // }
    }

    public function OndayOrderAssignment(Request $request){
        try{
            
            $latitude = $request->input('latitude');
            $longitude = $request->input('longitude');
            if(empty($latitude) || empty($longitude)){
                return $this->sendError('Latitude and Longitude are required', 400);
            }
            if(!is_numeric($latitude) || !is_numeric($longitude)){
                return $this->sendError('Latitude and Longitude must be numeric', 400);
            }

            $typeGravier = $request->input('type_gravier');
            if(empty($typeGravier)){
                return $this->sendError('Type Gravier is required', 400);
            }

            $ondayOrderAssignment = new OrderAssignmentV1Service();
            $ondayOrderAssignment = $ondayOrderAssignment->ondayOrderAssignment($latitude, $longitude, $typeGravier);

            return $this->sendResponse($ondayOrderAssignment, 'Onday Order Assignment found successfully');
        }catch (\Exception $e){
            return $this->sendError($e->getMessage(), 400);
        }
    }


    public function assign(Request $request)
    {
        $startTime = microtime(true);
        $order = Order::find($request->input('order_id'));

        if (empty($order)) {
            return $this->sendError('Order not found');
        }

        $orderInfo = [
            'order_id' => $order->id,
            'user' => $order->user,
            'status' => $order->status,
            'pickup_location' => $order->pickup_location,
            'destination_location' => $order->destination_location,
            'assigned_at' => now(),
            'limit' => $request->input('limit'),
            'max_distance' => $request->input('max_distance')
        ];

        dispatch(function () use ($order, $request) {
            NotifyDriverForOrder::dispatchSync(
                $order->id,
                $request->input('limit'),
                $request->input('max_distance')
            );
        })->afterResponse();
        
        $endTime = microtime(true);
        Log::info("Temps d'execution assign : ".($endTime - $startTime)." secondes");

        return $this->sendResponse($orderInfo, 'Order assigned successfully');
    }
}
