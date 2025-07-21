<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\AppBaseController;
use Illuminate\Http\Request;
use App\Services\OrderAssignmentV1Service;
use App\Services\OrderAssignmentV2Service;

class TestAPIController extends AppBaseController
{
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
        try{
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
        }catch (\Exception $e){
            return $this->sendError($e->getMessage(), 400);
        }
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
}
