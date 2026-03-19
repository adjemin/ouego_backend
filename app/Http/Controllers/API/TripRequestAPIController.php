<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\TripRequest;
use App\Models\TripDriverAttempt;
use App\Events\CustomerNotificationCreated;
use App\Models\CustomerNotification;
use App\Models\RoutePoint;
use App\Models\OrderInvitation;



class TripRequestAPIController extends Controller
{
     public function  accept($id, Request $request){
        /** @var TripDriverAttempt $orderInvitation */
        $orderInvitation = TripDriverAttempt::find($id);

        if (empty($orderInvitation)) {
            return $this->sendError('Order Attempt not found', 400);
        }

        if($orderInvitation->status != TripDriverAttempt::ACCEPTED){
            return $this->sendError('You can only accept an order already accepted', 400);
        }

        if($$orderInvitation->tripRequest->status == TripRequest::ACCEPTED){
            return $this->sendError('Order already accepted', 400);
        }
        
        $order = Order::find($orderInvitation->order_id);
        if($order != null && $order->is_completed){
            $orderInvitation->status = TripDriverAttempt::TIMEOUT;
            $orderInvitation->save();
            return $this->sendError('Order already completed', 400);

        }

        if($orderInvitation->status == TripDriverAttempt::NOTIFIED){
            // Accept the order
            $orderInvitation->status = TripDriverAttempt::ACCEPTED;
            $orderInvitation->save();

            $orderInvitation->tripRequest()->update(
                [
                    "status" => TripRequest::ACCEPTED,
                    "scheduled_at" => now()
                ]
            );
            
    
            /** @var Order $order */
            $order = $orderInvitation->order();

            if($order != null){

                $order->update(
                    [
                        "driver_id" => $orderInvitation->driver_id,
                        "status" => Order::PERFORMER_FOUND,
                        "acceptation_time" => now()
                    ]
                );

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
            $others_task_invitations = TripDriverAttempt::where([
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
        /** @var OrderInvitation $orderInvitation */
        $orderInvitation = OrderInvitation::find($id);

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
                'driver_id' => $orderInvitation->driver_id,
                "is_waiting_acceptation" => true
            ])->get();

            return $this->sendResponse($orderInvitations->toArray(), 'Order Invitation retrieved successfully');
        }else{
            return $this->sendError('Affectation déjà traitée');
        }
    }
}
