<?php

namespace App\Services;

use App\Models\Service;
use App\Models\Order;
use App\Models\DeliveryType;
use App\Services\DriverEnjourneeAssignmentService;
use App\Services\DriverExpressAssignmentService;
use App\Services\DriverEnSemaineAssignmentService;
use App\Services\DriverNuitAssignmentService;
use App\Services\DriverLocationAssignmentService;
use Illuminate\Support\Facades\Log;
class DriverAssignmentService {

    function sendInvitations(Order $order, int $distance = 10){
        Log::info("DriverAssignmentService: Commande #{$order->id} - tentative d'assignation lancée dans un rayon de {$distance}km.");

        // Commandes de location : traitement dédié indépendamment du delivery_type_code
        if ($order->is_location) {
            Log::info("DriverAssignmentService: Commande #{$order->id} est une location — redirection vers DriverLocationAssignmentService.");
            app(DriverLocationAssignmentService::class)->findEligibleDriversForLocation($order, $distance);
            return;
        }

        if($order->delivery_type_code == DeliveryType::TYPE_EXPRESS){
            $expressService = app(DriverExpressAssignmentService::class);
            if($order->service_slug == Service::COURSE || $order->service_slug == Service::LOCATION)  {
                $expressService->assignCourseAndLocationNearestDriver($order, $distance);
            }

            if($order->service_slug == Service::AGREGATS_CONSTRUCTION)  {
                $expressService->getAggregatDriverAndNotify($order, $distance);
            }
        }

       if($order->delivery_type_code == DeliveryType::TYPE_EN_JOURNEE){
            // Assign driver to order
            $enjourneeService = app(DriverEnjourneeAssignmentService::class);
            if($order->service_slug == Service::COURSE || $order->service_slug == Service::LOCATION)  {
                $enjourneeService->assignCourseAndLocationNearestDriver($order, $distance);
            }

            if($order->service_slug == Service::AGREGATS_CONSTRUCTION)  {
                $enjourneeService->getAggregatDriverAndNotify($order, $distance);
            }
       }

       if($order->delivery_type_code == DeliveryType::TYPE_DE_SEMAINE){
            // Assign driver to order
            $enSemaineService = app(DriverEnSemaineAssignmentService::class);
            if($order->service_slug == Service::COURSE || $order->service_slug == Service::LOCATION)  {
                $enSemaineService->assignCourseAndLocationNearestDriver($order, $distance);
            }

            if($order->service_slug == Service::AGREGATS_CONSTRUCTION)  {
                $enSemaineService->getAggregatDriverAndNotify($order, $distance);
            }
       }

       if($order->delivery_type_code == DeliveryType::TYPE_DE_NUIT){
            // Les commandes de nuit : recherche de chauffeurs lancée automatiquement à partir de 20h
            if(now()->hour >= 20){
                $nuitService = app(DriverNuitAssignmentService::class);
                if($order->service_slug == Service::COURSE || $order->service_slug == Service::LOCATION)  {
                    $nuitService->assignCourseAndLocationNearestDriver($order, 10);
                }

                if($order->service_slug == Service::AGREGATS_CONSTRUCTION)  {
                    $nuitService->getAggregatDriverAndNotify($order, 10);
                }
            }
       }
    }
}