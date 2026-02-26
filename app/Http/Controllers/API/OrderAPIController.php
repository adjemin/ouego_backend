<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\API\CreateOrderAPIRequest;
use App\Http\Requests\API\UpdateOrderAPIRequest;
use App\Models\Order;
use App\Models\OrderInvitation;
use App\Models\OrderItem;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Commercial;
use App\Models\Product;
use App\Models\Payment;
use App\Models\ProductType;
use App\Models\TypeEngin;
use App\Models\TypeEnginModel;
use App\Models\RoutePoint;
use App\Models\Invoice;
use App\Models\Driver;
use App\Models\Carrier;
use App\Models\DriverNotification;
use App\Models\DeliveryType;
use App\Utilities\DriverNotificationsUtils;
use App\Repositories\OrderRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\AppBaseController;
use Carbon\Carbon;
use App\Utilities\PricingUtils;
use App\Utilities\GoogleMapsAPIUtils;
use App\Services\DriverAssignmentService;
use App\Services\CarrierLocationService;
use App\Services\OrangeSMSService;
/**
 * Class OrderAPIController
 */
class OrderAPIController extends AppBaseController
{
    private OrderRepository $orderRepository;
    private CarrierLocationService $carrierLocationService;
    private OrangeSMSService $orangeSMSService;

    public function __construct(
        OrderRepository $orderRepo, 
        CarrierLocationService $carrierLocationService,
        OrangeSMSService $orangeSMSService
    )
    {
        $this->orderRepository = $orderRepo;
        $this->carrierLocationService = $carrierLocationService;
        $this->orangeSMSService  = $orangeSMSService;
    }

    private function getCommercialDiscount($customer): array
    {
        $discount = 0;

        if (!empty($customer->code_commercial)) {
            $commercial = Commercial::where('code', $customer->code_commercial)->first();

            if ($commercial) {
                $endDays = Setting::get('COMMERCIAL_VALIDITY_DAYS') ?? '10';
                $endDate = Carbon::parse($customer->created_at)->addDays($endDays)->format('Y-m-d');
                $maxOrders = intval(Setting::get('COMMERCIAL_MAX_ORDERS') ?? 5);
                $discountAmount = doubleval(Setting::get('COMMERCIAL_DISCOUNT_AMOUNT') ?? 2500);

                if (Carbon::now()->lte(Carbon::parse($endDate))) {
                    $discountedOrdersCount = Invoice::where('customer_id', $customer->id)
                        ->where('coupon', $customer->code_commercial)
                        ->count();

                    if ($discountedOrdersCount < $maxOrders) {
                        $discount = $discountAmount;
                    }
                }
            }
        }

        return [
            'discount' => $discount,
            'has_commercial_discount' => $discount > 0,
        ];
    }

    /**
     * Display a listing of the Orders.
     * GET|HEAD /orders
     */
    public function index(Request $request): JsonResponse
    {
        $orders = $this->orderRepository->all(
            $request->except(['skip', 'limit']),
            $request->get('skip'),
            $request->get('limit')
        );

        return $this->sendResponse($orders->toArray(), 'Orders retrieved successfully');
    }

    /**
     * Store a newly created Order in storage.
     * POST /orders
     */
    public function store(CreateOrderAPIRequest $request): JsonResponse
    {
        $customer = auth('api-customers')->user();

        $input = $request->all();

        if(empty($request->input('payment_method_code'))){
            return $this->sendError('payment_method_code is required', 400);
        }

        if(empty($request->input('items'))){
            return $this->sendError('items is required', 400);
        }


        $items = (array) $request->input('items');

        // Verifier la disponibilité de la course le type de livraison
        if(count($items)){
            $meta_data = (array) $items[0]['meta_data'];
            $delivery_type_code = array_key_exists('delivery_type_code', $meta_data)?$meta_data['delivery_type_code']:null;

            if($delivery_type_code == DeliveryType::TYPE_EXPRESS){
                $now = now();
                // Plage Horaires interdites
                $start_morning = $now->copy()->setTime(6, 0);
                $end_morning   = $now->copy()->setTime(8, 59);

                $start_evening = $now->copy()->setTime(17, 0);
                $end_envening   = $now->copy()->setTime(19, 59);

                if ($now->gte($start_morning) && $now->lte($end_morning) || $now->gte($start_evening) && $now->lte($end_envening)) {
                    return $this->sendError("L’option Course Express est n'est pas disponible de de 06H00 à 08H59 et de 17H00 à 19H30.");
                }
            }

            // Limiter la course en journée à partir de 12H
            if($delivery_type_code == DeliveryType::TYPE_EN_JOURNEE){
                $cutoffHour = intval(Setting::get('JOURNEE_CUTOFF_HOUR'))?? 12;
                if(now()->hour < 6 || now()->hour > $cutoffHour){
                    return $this->sendError("Vous pouvez passer une course en journée uniquement de 06H00 à {$cutoffHour}H00.");
                }
            }

            // Limiter la course en semaine uniquement du lundi au jeudi
            if($delivery_type_code == DeliveryType::TYPE_DE_SEMAINE){
                $dayOfWeekIso = now()->dayOfWeekIso;
                if (!in_array($dayOfWeekIso, [1, 2, 3, 4], true)) {
                    return $this->sendError("Les courses en semaine ne peuvent être lancées que du lundi au jeudi.");
                }
            }

            if($delivery_type_code == DeliveryType::TYPE_DE_NUIT){
                $now = now();
                $start = $now->copy()->setTime(7, 0);
                $end   = $now->copy()->setTime(19, 30);

                if ($now->lt($start) || $now->gt($end)) {
                    return $this->sendError("L’option Course De nuit est disponible uniquement de 07H00 à 19H30.");
                }
            }
        }

        $order = Order::create([
            "reference" => Order::generateReference(),
            "customer_id" => $customer->id,
            "status" => Order::INITIATED,
            'order_object' => $request->input('order_object'),
            "is_started" => false,
            "is_running" => false,
            "is_waiting" => true,
            "is_completed" => false,
            "completion_time" => null,
            "start_time" => null,
            "acceptation_time" => null,
            "expected_arrival_at" => null,
            'rating_id' => null,
            'rating'=> null,
            'rating_note'=> null,
            'order_price' => 0,
            'currency_code' => 'XOF',
            'payment_method_code' => $request->input('payment_method_code'),
            'delivery_type_code'=> null,
            'is_location' => null,
            'is_product' => null,
            'is_ride' => null
        ]);

        // Register order history
        $order->newOrderHistory(Order::INITIATED, $customer->table, $customer->id);


        foreach ($items as $item) {

            $item = (array)$item;

            if(!array_key_exists('service_slug',$item)){
                $order->forceDelete();
                return $this->sendError('service_slug is required', 400);
            }

            $service = Service::where('slug', $item["service_slug"])->first();

            if($service == null){
                $order->forceDelete();
                return $this->sendError('Service not found', 400);
            }

            $meta_data = [];

            if($service->slug == Service::COURSE){


                /**
                 *
                 *     {
                        "service_slug":"course",
                        "meta_data":{
                            "type_engin_slug":"camion-benne",
                            "engin_model":"6-roues",
                            "delivery_type_code":"EXPRESS"
                        },
                        "route_points":[
                            {
                                "address_name":"Cocody, Abidjan, Côte d'ivoire",
                                "latitude":4,
                                "longitude":-5,
                                "type":"source",
                                "parcel_details":""
                            },
                            {
                                "address_name":"Koumassi, Abidjan, Côte d'ivoire",
                                "latitude":4,
                                "longitude":-5,
                                "type":"destination",
                                "parcel_details":""
                            }
                        ]
                    }
                 *
                 */

                 if(!array_key_exists('meta_data',$item)){
                    $order->forceDelete();
                    return $this->sendError('meta_data is required', 400);
                }

                if(!array_key_exists('route_points',$item)){
                    $order->forceDelete();
                    return $this->sendError('route_points is required', 400);
                }

                $meta_data = $item['meta_data'];
                if(!is_array($meta_data)){
                    $meta_data = (array) $item['meta_data'];
                }

                if(!array_key_exists('type_engin_slug',$meta_data)){
                    $order->forceDelete();
                    return $this->sendError('type_engin_slug is required', 400);
                }

                if(!array_key_exists('engin_model',$meta_data)){
                    $order->forceDelete();
                    return $this->sendError('engin_model is required', 400);
                }

                if(!array_key_exists('delivery_type_code',$meta_data)){
                    $order->forceDelete();
                    return $this->sendError('delivery_type_code is required', 400);
                }

                $delivery_type_code = array_key_exists('delivery_type_code', $meta_data)?$meta_data['delivery_type_code']:'EXPRESS';

                $delivery_fees = array_key_exists('delivery_fees', $meta_data)?$meta_data['delivery_fees']:0;

                if($delivery_fees == 0){
                    $delivery_fees = $this->getDeliveryFeesForCourse((array)$item['route_points'], $meta_data['engin_model'], $delivery_type_code);
                }

                $manutention_pricing = array_key_exists('manutention_pricing', $meta_data)?$meta_data['manutention_pricing']:0; 

                $order->delivery_type_code = $delivery_type_code;
                $order->save();

                $commission_min = doubleval(Setting::get('OUEGO_COMMISSION_COURSE_MIN'));
                $commission = doubleval(Setting::get('COURSE_COMMISSION_OUEGO'));

    
                $service_due = $commission;
                if($service_due < $commission_min){
                    $service_due = $commission_min;
                }

                $driver_due = ($delivery_fees - $service_due) + $manutention_pricing;

                $total_amount = $delivery_fees + $manutention_pricing;


                $orderItem = OrderItem::create([
                    'order_id' => $order->id,
                    'service_slug' => $service->slug,
                    'meta_data' => [
                        "type_engin_slug" => array_key_exists('type_engin_slug', $meta_data)?$meta_data['type_engin_slug']:null,
                        "engin_model" => array_key_exists('engin_model', $meta_data)?$meta_data['engin_model']:null,
                        "delivery_type_code" => array_key_exists('delivery_type_code', $meta_data)?$meta_data['delivery_type_code']:null,
                    ],
                    'quantity' => 1,
                    'quantity_unity' => null,
                    'unit_price' => 0,
                    'order_price' => 0,
                    'delivery_price' => $delivery_fees,
                    'manutention_pricing' => $manutention_pricing,
                    'total_amount' => $total_amount,
                    'service_due' => $service_due,
                    'driver_due' => $driver_due,
                    'currency' => "XOF"
                ]);

                $order->service_slug = $service->slug;
                $order->save();


            }

            if($service->slug == Service::AGREGATS_CONSTRUCTION){

                /**
                 *
                 *     {
                        "service_slug":"agregats-construction",
                        "meta_data":{
                            "product_type_slug":"gravier-515-petit-grain",
                            "product_slug":"gravier",
                            "delivery_type_code":"EXPRESS",
                            "pricing":{
                                "name": "6 roues (8m3)",
                                "roues": 6,
                                "price": 25000
                            }
                        },
                        "quantity":1,
                        "delivery_price":22000,
                        "carrier_id":1,
                        "route_points":[
                            {
                                "address_name":"Koumassi, Abidjan, Côte d'ivoire",
                                "latitude":4,
                                "longitude":-5,
                                "type":"destination",
                                "parcel_details":""
                            }
                        ]
                    }
                 *
                 */

                 if(!array_key_exists('meta_data',$item)){
                    $order->forceDelete();
                    return $this->sendError('meta_data is required', 400);
                }

                if(!array_key_exists('route_points',$item)){
                    $order->forceDelete();
                    return $this->sendError('route_points is required', 400);
                }

                if(!array_key_exists('delivery_price',$item)){
                    $order->forceDelete();
                    return $this->sendError('delivery_price is required', 400);
                }

                if(!array_key_exists('carrier_id',$item)){
                    $order->forceDelete();
                    return $this->sendError('carrier_id is required', 400);
                }

                $meta_data = $item['meta_data'];
                if(!is_array($meta_data)){
                    $meta_data = (array) $item['meta_data'];
                }

                if(!array_key_exists('product_type_slug',$meta_data)){
                    $order->forceDelete();
                    return $this->sendError('product_type_slug is required', 400);
                }

                if(!array_key_exists('product_slug',$meta_data)){
                    $order->forceDelete();
                    return $this->sendError('product_slug is required', 400);
                }

                if(!array_key_exists('delivery_type_code',$meta_data)){
                    $order->forceDelete();
                    return $this->sendError('delivery_type_code is required', 400);
                }

                if(!array_key_exists('quantity',$item)){
                    $order->forceDelete();
                    return $this->sendError('quantity is required', 400);
                }

                if(!array_key_exists('product_type_slug', $meta_data)){
                    $order->forceDelete();
                    return $this->sendError('product_type_slug is required', 400);
                }

                if(!array_key_exists('product_slug', $meta_data)){
                    $order->forceDelete();
                    return $this->sendError('product_slug is required', 400);
                }

                if(!array_key_exists('delivery_type_code', $meta_data)){
                    $order->forceDelete();
                    return $this->sendError('delivery_type_code is required', 400);
                }


                $order->delivery_type_code = $meta_data['delivery_type_code'];

                $delivery_price = intval($item['delivery_price']);
                $order->delivery_price =  $delivery_price;
                $order->save();

                $productType = ProductType::where(['slug' => $meta_data['product_type_slug']])->first();
                if(empty($productType)) {
                    return $this->sendError('Type de produit introuvable', 400);
                }

                $product = Product::where(['id' => $productType->product_id])->first();
                if(empty($product)) {
                    return $this->sendError('Produit introuvable', 400);
                }

                $carrier =  Carrier::where(['id' => $item['carrier_id']])->first();
                if(empty($carrier)) {
                    return $this->sendError('Carrier introuvable', 400);
                }


                $quantity = intval($item['quantity']);

                $order_price = 0;

                $delivery_price = array_key_exists('delivery_price', $item)?$item['delivery_price']:0;
                $manutention_pricing = array_key_exists('manutention_pricing', $meta_data)?$meta_data['manutention_pricing']:0; 

                $total_amount = 0;
                $unit_price = 0;

                if($product->slug == Product::GRAVIER_SLUG){
                    $unit_price = doubleval($productType->price);

                    $order_price = $quantity * $unit_price;
                }


                if($product->slug == Product::SABLE_SLUG && array_key_exists('pricing', $meta_data)){
                    $pricing = $meta_data['pricing'];

                    if(!is_array($pricing)){
                        $pricing = (array) $meta_data['pricing'];
                    }
                    $unit_price = $pricing['price'];

                    $order_price = $pricing['price'];

                    $quantity = $pricing['roues'];
                }


                $total_amount = $order_price + $delivery_price + $manutention_pricing;

                $commission_min = 0;
                $commission = 0;

                if($product->slug == Product::GRAVIER_SLUG){
                    $commission_min = doubleval(Setting::get('GRAVIER_COMMISSION_OUEGO_MIN'));
                    $commission = doubleval(Setting::get('GRAVIER_COMMISSION_OUEGO'));
                }

                if($product->slug == Product::SABLE_SLUG){
                    $commission_min = doubleval(Setting::get('SABLE_COMMISSION_OUEGO_MIN'));
                    $commission = doubleval(Setting::get('SABLE_COMMISSION_OUEGO'));
                }

                $service_due =  $commission;
                if($service_due < $commission_min){
                    $service_due = $commission_min;
                }

                $driver_due = $total_amount - $service_due;

                $currency = $productType->currency_code;

                $orderItem = OrderItem::create([
                    'order_id' => $order->id,
                    'service_slug' => $service->slug,
                    'meta_data' => [
                        "product_type_slug" => array_key_exists('product_type_slug', $meta_data)?$meta_data['product_type_slug']:null,
                        "product_slug" => array_key_exists('product_slug', $meta_data)?$meta_data['product_slug']:null,
                        "delivery_type_code" => array_key_exists('delivery_type_code', $meta_data)?$meta_data['delivery_type_code']:null,
                        "pricing" => array_key_exists('pricing', $meta_data)?$meta_data['pricing']:null,
                    ],
                    'quantity' => $quantity,
                    'quantity_unity' => "T",
                    'unit_price' => $unit_price,
                    'order_price' => $order_price,
                    'delivery_price' => $delivery_price,
                    'manutention_pricing' => $manutention_pricing,
                    'total_amount' => $total_amount,
                    'service_due' => $service_due,
                    'driver_due' => $driver_due,
                    'currency' => $currency,
                    'carrier_id' => $item['carrier_id']
                ]);

                $order->service_slug = $service->slug;
                $order->save();

                //Créer une RoutePoint avec carrier_id
                $sourceRoutePoint = RoutePoint::create([
                    'customer_id' => null,
                    'order_id' => $order->id,
                    'address_name' => $carrier->name,
                    'latitude' => $carrier->location_latitude,
                    'longitude' => $carrier->location_longitude,
                    'contact_fullname' => null,
                    'contact_phone' => null,
                    'contact_email' => null,
                    'parcel_details' => null,
                    'type' => "source",
                    'status' => RoutePoint::WAITING,
                    'delivery_fees' => 0,
                    'currency_code' => 'XOF',
                    'is_waiting' => true,
                    'is_completed' => false,
                    'is_successful' => false,
                    'has_cash_management' => false,
                    'has_cash_deposited' => false,
                    'is_driver_paid' => false,
                    'completion_time'=> null,
                    'expected_arrival_at' => null,
                    'visit_order' => 1,
                    'stage' => null,
                    'apartment' => null
                ]);



            }

            if($service->slug == Service::LOCATION){

             /**
                 *
                 *     {
                            "service_slug":"location",
                            "meta_data":{
                                "type_engin_slug":"camion-benne",
                                "engin_model":"6-roues"
                            },
                            "quantity":1,
                            "location_start_date":"2024-05-31",
                            "location_end_date":"2024-06-05",
                            "route_points":[
                                {
                                    "address_name":"Koumassi, Abidjan, Côte d'ivoire",
                                    "latitude":4,
                                    "longitude":-5,
                                    "type":"destination",
                                    "parcel_details":""
                                }
                            ]
                        }
                 *
                 */

                 if(!array_key_exists('meta_data',$item)){
                    $order->forceDelete();
                    return $this->sendError('meta_data is required', 400);
                }

                if(!array_key_exists('route_points',$item)){
                    $order->forceDelete();
                    return $this->sendError('route_points is required', 400);
                }

                $meta_data = $item['meta_data'];
                if(!is_array($meta_data)){
                    $meta_data = (array) $item['meta_data'];
                }

                if(!array_key_exists('type_engin_slug',$meta_data)){
                    $order->forceDelete();
                    return $this->sendError('type_engin_slug is required', 400);
                }

                if(!array_key_exists('engin_model',$meta_data)){
                    $order->forceDelete();
                    return $this->sendError('engin_model is required', 400);
                }

                if(!array_key_exists('quantity',$item)){
                    $order->forceDelete();
                    return $this->sendError('quantity is required', 400);
                }

                if(!array_key_exists('location_start_date',$item)){
                    $order->forceDelete();
                    return $this->sendError('location_start_date is required', 400);
                }

                if(!array_key_exists('location_end_date',$item)){
                    $order->forceDelete();
                    return $this->sendError('location_end_date is required', 400);
                }

                $location_start_date = Carbon::parse($item["location_start_date"]);
                $location_end_date = Carbon::parse($item["location_end_date"]);

                $typeEngin = TypeEngin::where(['slug' => $meta_data['type_engin_slug']])->first();
                $typeEnginModel = TypeEnginModel::where(['slug' => $meta_data['engin_model']])->first();

                if($typeEngin == null){
                    $order->forceDelete();
                    return $this->sendError('type_engin_slug not found', 400);
                }

                if($typeEnginModel == null){
                    $order->forceDelete();
                    return $this->sendError('engin_model not found', 400);
                }

                $quantity = $location_start_date->diffInDays($location_end_date);

                $unit_price = doubleval($typeEnginModel->price);

                $order_price = $quantity * $unit_price;

                $delivery_price = array_key_exists('delivery_price', $item)?$item['delivery_price']:0;
                $manutention_pricing = array_key_exists('manutention_pricing', $meta_data)?$meta_data['manutention_pricing']:0; 

                $total_amount = $order_price + $delivery_price;

                $currency = $typeEnginModel->currency_code;

                $commission_min = doubleval(Setting::get('LOCATION_COMMISSION_OUEGO_MIN'));
                $commission = doubleval(Setting::get('LOCATION_COMMISSION_OUEGO'));
                $service_due = $commission;

                if($service_due < $commission_min){
                    $service_due = $commission_min;
                }

                $driver_due = $total_amount - $service_due;

                $total_amount = $total_amount + $manutention_pricing;


                $orderItem = OrderItem::create([
                    'order_id' => $order->id,
                    'service_slug' => $service->slug,
                    'meta_data' => [
                        "type_engin_slug" => array_key_exists('type_engin_slug', $meta_data)?$meta_data['type_engin_slug']:null,
                        "engin_model" => array_key_exists('engin_model', $meta_data)?$meta_data['engin_model']:null
                    ],
                    'quantity' => $quantity,
                    'quantity_unity' => "days",
                    'unit_price' => $unit_price,
                    'order_price' => $order_price,
                    'delivery_price' => $delivery_price,
                    'manutention_pricing' => $manutention_pricing,
                    'total_amount' => $total_amount,
                    'service_due' => $service_due,
                    'driver_due' => $driver_due,
                    'currency' => $currency,
                    'location_start_date' => $item["location_start_date"],
                    'location_end_date' => $item["location_end_date"]
                ]);

                $order->service_slug = $service->slug;
                $order->save();


            }

            $route_points = $item['route_points'];

            if(!is_array($route_points)){
                $route_points = (array)$route_points;
            }



            foreach ($route_points as $route_point) {

                if(is_array($route_point)){
                    $routePoint = RoutePoint::create([
                        'customer_id' => $customer->id,
                        'order_id' => $order->id,
                        'address_name' => array_key_exists('address_name',$route_point)?$route_point['address_name']:null,
                        'latitude' => array_key_exists('latitude',$route_point)?$route_point['latitude']:null,
                        'longitude' => array_key_exists('longitude',$route_point)?$route_point['longitude']:null,
                        'contact_fullname' => array_key_exists('contact_fullname',$route_point)?$route_point['contact_fullname']:null,
                        'contact_phone' => array_key_exists('contact_phone',$route_point)?$route_point['contact_phone']:null,
                        'contact_email' => array_key_exists('contact_email',$route_point)?$route_point['contact_email']:null,
                        'parcel_details' => array_key_exists('parcel_details',$route_point)?$route_point['parcel_details']:null,
                        'type' => array_key_exists('type',$route_point)?$route_point['type']:null,
                        'status' => RoutePoint::WAITING,
                        'images' =>  array_key_exists('images',$route_point)?$route_point['images']:null,
                        'delivery_fees' => 0,
                        'currency_code' => 'XOF',
                        'is_waiting' => true,
                        'is_completed' => false,
                        'is_successful' => false,
                        'has_cash_management' => false,
                        'has_cash_deposited' => false,
                        'is_driver_paid' => false,
                        'completion_time'=> null,
                        'expected_arrival_at' => null,
                        'visit_order' => array_key_exists('visit_order', $route_point)?$route_point['visit_order']:null,
                        'stage' => array_key_exists('stage', $route_point)?$route_point['stage']:null,
                        'apartment' => array_key_exists('apartment', $route_point)?$route_point['apartment']:null,
                        'has_handling' => array_key_exists('has_handling', $route_point)?$route_point['has_handling']:null
                    ]);
                }

            }

        }

        $order_items = OrderItem::where('order_id', $order->id)->get();

        $order_price = 0;
        $delivery_price = 0;
        $manutention_pricing = 0;
        $driver_due = 0;
        $service_due = 0;

        foreach($order_items as $order_item){

            $driver_due = $driver_due + $order_item->driver_due;
            $service_due = $service_due + $order_item->service_due;

            if($order_item->service_slug == Service::COURSE){
                $order->is_ride = true;
                $order->save();

                $order_price = $order_price + $order_item->delivery_price;
                $delivery_price = $delivery_price + $order_item->delivery_price;
                $manutention_pricing = $manutention_pricing + $order_item->manutention_pricing;
            }

            if($order_item->service_slug == Service::AGREGATS_CONSTRUCTION){
                $order->is_product = true;
                $order->save();

                $order_price = $order_price + $order_item->order_price;
                $delivery_price = $delivery_price + $order_item->delivery_price;
                $manutention_pricing = $manutention_pricing + $order_item->manutention_pricing;
            }

            if($order_item->service_slug == Service::LOCATION){
                $order->is_location = true;
                $order->save();

                $order_price = $order_price + $order_item->order_price;
                $delivery_price = $delivery_price + $order_item->delivery_price;
                $manutention_pricing = $manutention_pricing + $order_item->manutention_pricing;
            }

        }

        $order->order_price = $order_price;
        $order->delivery_price = $delivery_price;
        $order->manutention_pricing = $manutention_pricing;
        $order->save();

        $subtotal = $order->order_price;
        $tax = 0;
        $fees_delivery = $order->delivery_price;
        $invoice_total = 0;
        if($order->service_slug == Service::COURSE){
            $invoice_total = $subtotal + $tax ;
        }else{
            $invoice_total = $subtotal + $fees_delivery+ $tax;
        }

        $discount = 0;
        $coupon = null;

        if (!empty($customer->code_commercial)) {
            $commercial = Commercial::where('code', $customer->code_commercial)->first();

            if ($commercial) {
                $endDays = Setting::get('COMMERCIAL_VALIDITY_DAYS') ?? '30';
                $endDate = Carbon::parse($customer->created_at)->addDays($endDays)->format('Y-m-d');
                $maxOrders = intval(Setting::get('COMMERCIAL_MAX_ORDERS') ?? 5);
                $discountAmount = doubleval(Setting::get('COMMERCIAL_DISCOUNT_AMOUNT') ?? 2500);
                $creditAmount = doubleval(Setting::get('COMMERCIAL_CREDIT_AMOUNT') ?? 2500);

                if (Carbon::now()->lte(Carbon::parse($endDate))) {
                    $discountedOrdersCount = Invoice::where('customer_id', $customer->id)
                        ->where('coupon', $customer->code_commercial)
                        ->count();

                    if ($discountedOrdersCount < $maxOrders) {
                        $discount = $discountAmount;
                        $invoice_total = max(0, $invoice_total - $discount);
                        $commercial->creditBalance($creditAmount);
                        $coupon = $customer->code_commercial;
                        $this->orangeSMSService->sendSMS("+" . $commercial->phone, "OUEGO: Une nouvelle commande d'un client est arrivée. Votre compte a été credité de {$creditAmount} XOF. Votre nouveau solde est de {$commercial->current_balance} XOF.");
                    }
                }
            }
        }

        $invoice = Invoice::create([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'reference' => Invoice::generateID("ORDER", $order->id, $customer->id),
            'subtotal' => $subtotal,
            'tax' => $tax,
            'fees_delivery' => $fees_delivery,
            'fees_manutention' => $manutention_pricing,
            'total' => $invoice_total,
            'status' => Invoice::UNPAID,
            'is_paid_by_customer' => false,
            'is_paid_by_delivery_service' => false,
            'currency_slug' => 'XOF',
            'driver_due' => $driver_due,
            'service_due' => $service_due,
            'discount' => $discount,
            'coupon' => $coupon
        ]);


        /** @var Order $order */
        $order = $this->orderRepository->find($order->id);


        return $this->sendResponse($order->toArray(), 'Order saved successfully');
    }

    public function pay($id, Request $request){

        /** @var Order $order */
        $order = $this->orderRepository->find($id);

        if (empty($order)) {
            return $this->sendError('Order not found');
        }

        $invoice = Invoice::where('order_id', $order->id)->first();

        if (empty($invoice)) {
            return $this->sendError('Invoice not found');
        }

        if($invoice->status == Invoice::PAID){
            return $this->sendError('Invoice already paid', 400);
        }

        //Create Payment
        $payment = Payment::create([
            'payment_method_code' => 'online',
            'invoice_id' => $invoice->id,
            'user_id' => $order->customer_id,
            'payment_reference' => Payment::generateReference(),
            'amount' => 100,
            'currency_code' => 'XOF',
            'status' => Payment::STATUS_PENDING,
            'is_waiting' => true,
            'is_completed' => false
        ]);

        return $this->sendResponse($payment->toArray(), 'Payment saved successfully');

    }

    /**
     * Display the specified Order.
     * GET|HEAD /orders/{id}
     */
    public function show($id): JsonResponse
    {
        /** @var Order $order */
        $order = $this->orderRepository->find($id);

        if (empty($order)) {
            return $this->sendError('Order not found');
        }

        return $this->sendResponse($order->toArray(), 'Order retrieved successfully');
    }

    /**
     * Update the specified Order in storage.
     * PUT/PATCH /orders/{id}
     */
    public function update($id, UpdateOrderAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        /** @var Order $order */
        $order = $this->orderRepository->find($id);

        if (empty($order)) {
            return $this->sendError('Order not found');
        }

        $order = $this->orderRepository->update($input, $id);

        return $this->sendResponse($order->toArray(), 'Order updated successfully');
    }

    /**
     * Remove the specified Order from storage.
     * DELETE /orders/{id}
     *
     * @throws \Exception
     */
    public function destroy($id): JsonResponse
    {
        /** @var Order $order */
        $order = $this->orderRepository->find($id);

        if (empty($order)) {
            return $this->sendError('Order not found');
        }

        $order->delete();

        return $this->sendSuccess('Order deleted successfully');
    }

    public function getCustomerOrders(Request $request){

        $customer = auth('api-customers')->user();

        $orders = Order::where('customer_id', $customer->id)->orderBy('created_at', 'desc')->take(10)->get();

        return $this->sendResponse($orders->toArray(), 'Orders retrieved successfully');

    }

    public function getDriverOrders(Request $request){

        $driver = auth('api-drivers')->user();

        $orders = Order::where('driver_id', $driver->id)->orderBy('created_at', 'desc')->get();

        return $this->sendResponse($orders->toArray(), 'Orders retrieved successfully');

    }

    public function estimateRidePrice(Request $request){

                /**
                 *
                 *     {
                        "service_slug":"course",
                        "meta_data":{
                            "type_engin_slug":"camion-benne",
                            "engin_model":"6-roues",
                            "delivery_type_code":"EXPRESS"
                        },
                        "route_points":[
                            {
                                "address_name":"Cocody, Abidjan, Côte d'ivoire",
                                "latitude":4,
                                "longitude":-5,
                                "type":"source",
                                "parcel_details":""
                            },
                            {
                                "address_name":"Koumassi, Abidjan, Côte d'ivoire",
                                "latitude":4,
                                "longitude":-5,
                                "type":"destination",
                                "parcel_details":""
                            }
                        ]
                    }
                 *
                 */



                 if(!array_key_exists('meta_data', $request->all())){

                    return $this->sendError('meta_data is required', 400);
                }

                if(!array_key_exists('route_points', $request->all())){

                    return $this->sendError('route_points is required', 400);
                }

                $meta_data = $request->input('meta_data');

                $route_points = $request->input('route_points');

                if(!is_array($meta_data)){
                    $meta_data = (array) $meta_data;
                }

                if(!is_array($route_points)){
                    $route_points = (array) $route_points;
                }

                if(!array_key_exists('type_engin_slug',$meta_data)){

                    return $this->sendError('type_engin_slug is required', 400);
                }

                if(!array_key_exists('engin_model',$meta_data)){

                    return $this->sendError('engin_model is required', 400);
                }

                if(!array_key_exists('delivery_type_code',$meta_data)){

                    return $this->sendError('delivery_type_code is required', 400);
                }

                $typeEnginModel = TypeEnginModel::where('slug', $meta_data['engin_model'])->first();

                if(empty($typeEnginModel)){
                    return $this->sendError('engin_model is required', 400);
                }

                $delivery_type_code = $meta_data['delivery_type_code'];

                $source_list = collect([]);
                $destination_list = collect([]);

                foreach ($route_points as $route_point_item){
                    if(!is_array($route_point_item)){
                        $route_point_item = (array)$route_point_item;
                    }

                    $route_point_item_type = array_key_exists('type', $route_point_item)?$route_point_item['type']:null;

                    if($route_point_item_type == 'source'){
                        $source_list->push($route_point_item);
                    }

                    if($route_point_item_type == 'destination'){
                        $destination_list->push($route_point_item);
                    }

                }

                $source_point = $source_list->first();

                $destination_point = $destination_list->last();

                $result = GoogleMapsAPIUtils::getDistance([
                    $source_point['latitude'],
                    $source_point['longitude'],
                ],[
                    $destination_point['latitude'],
                    $destination_point['longitude'],
                ]);



                $current_distance = 0;

                if(array_key_exists('distance',$result)){
                    $result_distance = $result['distance']; //array
                    $result_distance_value = $result_distance['value']; //meters
                    $current_distance = $result_distance_value/1000; //kilometers
                    $current_distance = $current_distance;

                }
                $duration = "";

                if(array_key_exists('duration',$result)){
                    $result_duration = $result['duration']; //array
                    $duration = $result_duration['text'];
                }

                //$current_distance = 418;

                $delivery_type_code = "EXPRESS";
                $amount = PricingUtils::transportCourse($current_distance, $typeEnginModel, $delivery_type_code);

                // Vérifier la disponibilité de l'option express en fonction de l'heure actuelle
                $now = now();
                $expressIsAvalable = true;
                $expressMessage = null;
                // Plage Horaires interdites
                $start_morning = $now->copy()->setTime(6, 0);
                $end_morning   = $now->copy()->setTime(8, 59);

                $start_evening = $now->copy()->setTime(17, 0);
                $end_envening   = $now->copy()->setTime(19, 59);

                if ($now->gte($start_morning) && $now->lte($end_morning) || $now->gte($start_evening) && $now->lte($end_envening)) {
                    $expressIsAvalable = false;
                    $expressMessage = "L’option Course Express est n'est pas disponible de de 06H00 à 08H59 et de 17H00 à 19H30.";
                }

                //EXPRESS
                $expressPricing = [
                    "distance" => $current_distance,
                    "duration" => $duration,
                    "amount" => $amount,
                    "delivery_type" => DeliveryType::where('slug', $delivery_type_code)->first(),
                    "is_available" => $expressIsAvalable,
                    "error_message" => $expressMessage
                ];



                //En journée
                $delivery_type_code = "en-journee";
                $amount = PricingUtils::transportCourse($current_distance, $typeEnginModel, $delivery_type_code);
                
                // Limiter la course en journée à partir de 12H
                $isJourneeAvailable = true;
                $journeeErrorMessage = null;
                if($delivery_type_code == DeliveryType::TYPE_EN_JOURNEE){
                    $cutoffHour = intval(Setting::get('JOURNEE_CUTOFF_HOUR'))?? 12;
                    if(now()->hour < 6 || now()->hour > $cutoffHour){
                        $isJourneeAvailable = false;
                        $journeeErrorMessage = "Vous pouvez passer une course en journée uniquement de 06H00 à {$cutoffHour}H00.";
                    }
                }

                $sameDayPricing = [
                    "distance" => $current_distance,
                    "duration" => $duration,
                    "amount" => $amount,
                    "delivery_type" => DeliveryType::where('slug', $delivery_type_code)->first(),
                    "is_available" => $isJourneeAvailable,
                    "error_message" => $journeeErrorMessage
                ];

                //De nuit
                $delivery_type_code = "de-nuit";
                $amount = PricingUtils::transportCourse($current_distance, $typeEnginModel, $delivery_type_code);

                // Limiter la course de nuit uniquement de 07H00 à 19H30
                $isDeNuitAvailable = true;
                $deNuitErrorMessage = null;
                if($delivery_type_code == DeliveryType::TYPE_DE_NUIT){
                    $now = now();
                    $start = $now->copy()->setTime(7, 0);
                    $end   = $now->copy()->setTime(19, 30);

                    if ($now->lt($start) || $now->gt($end)) {
                        $isDeNuitAvailable = false;
                        $deNuitErrorMessage = "L’option Course De nuit est disponible uniquement de 07H00 à 19H30.";
                    }
                }

                $sameNightPricing = [
                    "distance" => $current_distance,
                    "duration" => $duration,
                    "amount" => $amount,
                    "delivery_type" => DeliveryType::where('slug', $delivery_type_code)->first(),
                    "is_available" => $isDeNuitAvailable,
                    "error_message" => $deNuitErrorMessage
                ];

                //En semaine
                $delivery_type_code = "en-semaine";
                $amount = PricingUtils::transportCourse($current_distance, $typeEnginModel, $delivery_type_code);

                // Limiter la course en semaine uniquement du lundi au jeudi
                $isEnSemaineAvailable = true;
                $enSemaineErrorMessage = null;
                if($delivery_type_code == DeliveryType::TYPE_DE_SEMAINE){
                    $dayOfWeekIso = now()->dayOfWeekIso;
                    if (!in_array($dayOfWeekIso, [1, 2, 3, 4], true)) {
                        $isEnSemaineAvailable = false;
                        $enSemaineErrorMessage = "Les courses en semaine ne peuvent être lancées que du lundi au jeudi.";
                    }
                }
                $sameWeekPricing = [
                    "distance" => $current_distance,
                    "duration" => $duration,
                    "amount" => $amount,
                    "delivery_type" => DeliveryType::where('slug', $delivery_type_code)->first(),
                    "is_available" => $isEnSemaineAvailable,
                    "error_message" => $enSemaineErrorMessage
                ];

                $customer = auth('api-customers')->user();
                $commercialDiscount = $this->getCommercialDiscount($customer);

                return $this->sendResponse([
                    'pricings' => [
                        $expressPricing,
                        $sameDayPricing,
                        $sameNightPricing,
                        $sameWeekPricing
                    ],
                    'discount' => $commercialDiscount['discount'],
                    'has_commercial_discount' => $commercialDiscount['has_commercial_discount'],
                ], 'Order saved successfully');


    }

    public function estimateRidePriceNew(Request $request){

        /**
         *
         *     {
                "service_slug":"course",
                "meta_data":{
                    "type_engin_slug":"camion-benne",
                    "engin_model":"6-roues",
                    "delivery_type_code":"EXPRESS"
                },
                "route_points":[
                    {
                        "address_name":"Cocody, Abidjan, Côte d'ivoire",
                        "latitude":4,
                        "longitude":-5,
                        "type":"source",
                        "parcel_details":""
                    },
                    {
                        "address_name":"Koumassi, Abidjan, Côte d'ivoire",
                        "latitude":4,
                        "longitude":-5,
                        "type":"destination",
                        "parcel_details":""
                    }
                ]
            }
            *
            */



            if(!array_key_exists('meta_data', $request->all())){

            return $this->sendError('meta_data is required', 400);
        }

        if(!array_key_exists('route_points', $request->all())){

            return $this->sendError('route_points is required', 400);
        }

        $meta_data = $request->input('meta_data');

        $route_points = $request->input('route_points');

        if(!is_array($meta_data)){
            $meta_data = (array) $meta_data;
        }

        if(!is_array($route_points)){
            $route_points = (array) $route_points;
        }

        if(!array_key_exists('type_engin_slug',$meta_data)){

            return $this->sendError('type_engin_slug is required', 400);
        }

        if(!array_key_exists('engin_model',$meta_data)){

            return $this->sendError('engin_model is required', 400);
        }

        if(!array_key_exists('delivery_type_code',$meta_data)){

            return $this->sendError('delivery_type_code is required', 400);
        }

        $typeEnginModel = TypeEnginModel::where('slug', $meta_data['engin_model'])->first();

        if(empty($typeEnginModel)){
            return $this->sendError('engin_model is required', 400);
        }

        $delivery_type_code = $meta_data['delivery_type_code'];

        $source_list = collect([]);
        $destination_list = collect([]);
        $route_arrets = collect([]);

        foreach ($route_points as $route_point_item){
            if(!is_array($route_point_item)){
                $route_point_item = (array)$route_point_item;
            }

            $route_point_item_type = array_key_exists('type', $route_point_item)?$route_point_item['type']:null;

            if($route_point_item_type == 'source'){
                $source_list->push($route_point_item);
            }

            if($route_point_item_type == 'destination'){
                $destination_list->push($route_point_item);
            }

            if($route_point_item_type == 'arret'){
                $route_arrets->push($route_point_item);
            }
        }


        // Initialize Values data
        $amount = 0;
        $current_distance = 0;
        $duration = 0;

        $source_point = $source_list->first();
        $destination_point = $destination_list->last();

        
        if(!$route_arrets->count()){
            $result = GoogleMapsAPIUtils::getDistance([
                $source_point['latitude'],
                $source_point['longitude'],
            ],[
                $destination_point['latitude'],
                $destination_point['longitude'],
            ]);

            if(array_key_exists('distance',$result)){
                $result_distance = $result['distance']; //array
                $result_distance_value = $result_distance['value']; //meters
                $distance = $result_distance_value/1000; //kilometers
                $current_distance = $current_distance + $distance;
            }

            if(array_key_exists('duration',$result)){
                $result_duration = $result['duration']; //array
                $duration = intval($result_duration['value']);
            }
        }
        

        
        if($route_arrets->count()){
            
            $last_arret = null;
            if(!is_array($route_arrets) && count($route_arrets) == 1){

                $arret_point = $route_arrets->first();
                $result = GoogleMapsAPIUtils::getDistance([
                    $source_point['latitude'],
                    $source_point['longitude'],
                ],[
                    $arret_point['latitude'],
                    $arret_point['longitude'],
                ]);

                if(array_key_exists('distance',$result)){
                    $result_distance = $result['distance']; //array
                    $result_distance_value = $result_distance['value']; //meters
                    $distance = $result_distance_value/1000; //kilometers
                    $current_distance = $current_distance + $distance;
                }

                if(array_key_exists('duration',$result)){
                    $result_duration = $result['duration']; //array
                    $duration = $duration + intval($result_duration['value']);
                }

                
                $last_arret = $arret_point;

            }elseif(!is_array($route_arrets)){
                foreach ($route_arrets as $index => $route_arret){
                    
                    if($index == 0){
                        $result = GoogleMapsAPIUtils::getDistance([
                            $source_point['latitude'],
                            $source_point['longitude'],
                        ],[
                            $route_arret['latitude'],
                            $route_arret['longitude'],
                        ]); 
                        
                    }else{
                        $result = GoogleMapsAPIUtils::getDistance([
                            $last_arret['latitude'],
                            $last_arret['longitude'],
                        ],[
                            $route_arret['latitude'],
                            $route_arret['longitude'],
                        ]);

                    }

                    if(array_key_exists('distance',$result)){
                        $result_distance = $result['distance']; //array
                        $result_distance_value = $result_distance['value']; //meters
                        $distance = $result_distance_value/1000; //kilometers
                        $current_distance = $current_distance + $distance;
                    }

                    if(array_key_exists('duration',$result)){
                        $result_duration = $result['duration']; //array
                        $duration = $duration + intval($result_duration['value']);
                    }

                    // Update the last arret
                    $last_arret = $route_arret;
                }
                
            }

            $result = GoogleMapsAPIUtils::getDistance([
                $last_arret['latitude'],
                $last_arret['longitude'],
            ],[
                $destination_point['latitude'],
                $destination_point['longitude'],
            ]); 

            if(array_key_exists('distance',$result)){
                $result_distance = $result['distance']; //array
                $result_distance_value = $result_distance['value']; //meters
                $distance = $result_distance_value/1000; //kilometers
                $current_distance = $current_distance + $distance;
            }

            if(array_key_exists('duration',$result)){
                $result_duration = $result['duration']; //array
                $duration = $duration + intval($result_duration['value']);
            }
        }

        $duration = strval($duration). " seconds";


        $delivery_type_code = "EXPRESS";
        $amount = PricingUtils::transportCourse($current_distance, $typeEnginModel, $delivery_type_code);

        // Vérifier la disponibilité de l'option express en fonction de l'heure actuelle
        $now = now();
        $expressIsAvalable = true;
        $expressMessage = null;
        // Plage Horaires interdites
        $start_morning = $now->copy()->setTime(6, 0);
        $end_morning   = $now->copy()->setTime(8, 59);

        $start_evening = $now->copy()->setTime(17, 0);
        $end_envening   = $now->copy()->setTime(19, 59);

        if ($now->gte($start_morning) && $now->lte($end_morning) || $now->gte($start_evening) && $now->lte($end_envening)) {
            $expressIsAvalable = false;
            $expressMessage = "L’option Course Express est n'est pas disponible de de 06H00 à 08H59 et de 17H00 à 19H30.";
        }

        //EXPRESS
        $expressPricing = [
            "distance" => $current_distance,
            "duration" => $duration,
            "amount" => $amount,
            "delivery_type" => DeliveryType::where('slug', $delivery_type_code)->first()
        ];



        //En journée
        $delivery_type_code = "en-journee";
        $amount = PricingUtils::transportCourse($current_distance, $typeEnginModel, $delivery_type_code);
        $sameDayPricing = [
            "distance" => $current_distance,
            "duration" => $duration,
            "amount" => $amount,
            "delivery_type" => DeliveryType::where('slug', $delivery_type_code)->first()
        ];

        //De nuit
        $delivery_type_code = "de-nuit";
        $amount = PricingUtils::transportCourse($current_distance, $typeEnginModel, $delivery_type_code);
        $sameNightPricing = [
            "distance" => $current_distance,
            "duration" => $duration,
            "amount" => $amount,
            "delivery_type" => DeliveryType::where('slug', $delivery_type_code)->first()
        ];

        //En semaine
        $delivery_type_code = "en-semaine";
        $amount = PricingUtils::transportCourse($current_distance, $typeEnginModel, $delivery_type_code);
        $sameWeekPricing = [
            "distance" => $current_distance,
            "duration" => $duration,
            "amount" => $amount,
            "delivery_type" => DeliveryType::where('slug', $delivery_type_code)->first()
        ];

        return $this->sendResponse([
            $expressPricing,
            $sameDayPricing,
            $sameNightPricing,
            $sameWeekPricing
        ], 'Order saved successfully');


    }

    public function getDeliveryFees(array $route_points, $delivery_type_code){

        /**
         *
         *     {
                "route_points":[
                    {
                        "address_name":"Cocody, Abidjan, Côte d'ivoire",
                        "latitude":4,
                        "longitude":-5,
                        "type":"source",
                        "parcel_details":""
                    },
                    {
                        "address_name":"Koumassi, Abidjan, Côte d'ivoire",
                        "latitude":4,
                        "longitude":-5,
                        "type":"destination",
                        "parcel_details":""
                    }
                ]
            }
         *
         */



        $source_list = collect([]);
        $destination_list = collect([]);

        foreach ($route_points as $route_point_item){
            if(!is_array($route_point_item)){
                $route_point_item = (array)$route_point_item;
            }

            $route_point_item_type = array_key_exists('type', $route_point_item)?$route_point_item['type']:null;

            if($route_point_item_type == 'source'){
                $source_list->push($route_point_item);
            }

            if($route_point_item_type == 'destination'){
                $destination_list->push($route_point_item);
            }


        }

        $source_point = $source_list->first();

        $destination_point = $destination_list->last();

        $result = GoogleMapsAPIUtils::getDistance([
            $source_point['latitude'],
            $source_point['longitude'],
        ],[
            $destination_point['latitude'],
            $destination_point['longitude'],
        ]);


        $current_distance = 0;

        if(array_key_exists('distance',$result)){
            $result_distance = $result['distance']; //array
            $result_distance_value = $result_distance['value']; //meters
            $current_distance = $result_distance_value/1000; //kilometers
            $current_distance = $current_distance;

        }


        return PricingUtils::transport($current_distance, $delivery_type_code);

    }
    public function getDeliveryFeesForCourse(array $route_points, string $engin_model, string $delivery_type_code){

        /**
         *
         *     {
                "route_points":[
                    {
                        "address_name":"Cocody, Abidjan, Côte d'ivoire",
                        "latitude":4,
                        "longitude":-5,
                        "type":"source",
                        "parcel_details":""
                    },
                    {
                        "address_name":"Koumassi, Abidjan, Côte d'ivoire",
                        "latitude":4,
                        "longitude":-5,
                        "type":"destination",
                        "parcel_details":""
                    }
                ]
            }
         *
         */


         $typeEnginModel = TypeEnginModel::where('slug', $engin_model)->first();

         if(empty($typeEnginModel)){
             return $this->sendError('engin_model is required', 400);
         }


        $source_list = collect([]);
        $destination_list = collect([]);

        foreach ($route_points as $route_point_item){
            if(!is_array($route_point_item)){
                $route_point_item = (array)$route_point_item;
            }

            $route_point_item_type = array_key_exists('type', $route_point_item)?$route_point_item['type']:null;

            if($route_point_item_type == 'source'){
                $source_list->push($route_point_item);
            }

            if($route_point_item_type == 'destination'){
                $destination_list->push($route_point_item);
            }


        }

        $source_point = $source_list->first();

        $destination_point = $destination_list->last();

        $result = GoogleMapsAPIUtils::getDistance([
            $source_point['latitude'],
            $source_point['longitude'],
        ],[
            $destination_point['latitude'],
            $destination_point['longitude'],
        ]);


        $current_distance = 0;

        if(array_key_exists('distance',$result)){
            $result_distance = $result['distance']; //array
            $result_distance_value = $result_distance['value']; //meters
            $current_distance = $result_distance_value/1000; //kilometers
            $current_distance = $current_distance;

        }


        return PricingUtils::transportCourse($current_distance, $typeEnginModel, $delivery_type_code);

    }

    public function confirm($id, Request $request){

        $customer = auth('api-customers')->user();

        /** @var Order $order */
        $order = $this->orderRepository->find($id);

        if (empty($order)) {
            return $this->sendError('Order not found');
        }

        if (empty($customer)) {
            return $this->sendError('Unauthorized', 401);
        }

        $input['is_draft'] = false;
        $input['order_date'] = now();
        $input['status'] = Order::PERFORMER_LOOKUP;

        $order->update($input);

        // Register order history
        $order->newOrderHistory(Order::PERFORMER_LOOKUP, $customer->table, $customer->id);

        // Recherche de chauffeurs et envoi d'invitations
        $expressService = app(DriverAssignmentService::class);
        $expressService->sendInvitations($order, 10);

        return $this->sendResponse($order->toArray(), 'Order updated successfully');
    }


    public function performDriverLookup($id, Request $request){
        /** @var Order $order */
        $order = $this->orderRepository->find($id);

        if (empty($order)) {
            return $this->sendError('Commande introuvable', 400);
        }

        if($order->driver_id == null || $order->acceptation_time == null){

                $orderInvitations = OrderInvitation::where([
                    'order_id' => $order->id,
                    "is_waiting_acceptation" => true
                ])->get();

                if(count($orderInvitations) == 0){
                    // Recherche de chauffeurs et envoi d'invitations
                    $expressService = app(DriverAssignmentService::class);
                    $expressService->sendInvitations($order, 10);
                }

        }

        return $this->sendResponse($order->toArray(), 'Order retrieved successfully');

    }

    public function estimateDeliveryPrice(Request $request){

        /**
         *
          {
            "service_slug":"agregats-construction",
            "meta_data":{
                "product_type_slug":"gravier-515-petit-grain",
                "product_slug":"gravier",
                "delivery_type_code":"EXPRESS"
            },
            "quantity":3,
            "route_points":[
                {
                    "address_name":"Pharmacie Sainte Monique du plateau dokui, Abidjan, Côte d'ivoire",
                    "latitude":5.3994128,
                    "longitude":-3.9999536,
                    "type":"destination",
                    "parcel_details":"",
                    "contact_fullname": "string",
                    "contact_phone":"string",
                    "contact_email":""
                }
            ]
          }
         *
         */



        if(!array_key_exists('meta_data', $request->all())){

            return $this->sendError('meta_data is required', 400);
        }

        if(!array_key_exists('quantity', $request->all())){

            return $this->sendError('quantity is required', 400);
        }

        if(!array_key_exists('route_points', $request->all())){

            return $this->sendError('route_points is required', 400);
        }

        $meta_data = $request->input('meta_data');

        $route_points = $request->input('route_points');

        if(!is_array($meta_data)){
            $meta_data = (array) $meta_data;
        }

        if(!is_array($route_points)){
            $route_points = (array) $route_points;
        }

        if(!array_key_exists('product_type_slug',$meta_data)){

            return $this->sendError('product_type_slug is required', 400);
        }

        if(!array_key_exists('product_slug',$meta_data)){

            return $this->sendError('product_slug is required', 400);
        }

        if(!array_key_exists('delivery_type_code',$meta_data)){

            return $this->sendError('delivery_type_code is required', 400);
        }

        $delivery_type_code = $meta_data['delivery_type_code'];

        $source_list = collect([]);
        $destination_list = collect([]);

        foreach ($route_points as $route_point_item){
            if(!is_array($route_point_item)){
                $route_point_item = (array)$route_point_item;
            }

            $route_point_item_type = array_key_exists('type', $route_point_item)?$route_point_item['type']:null;

            if($route_point_item_type == 'source'){
                $source_list->push($route_point_item);
            }

            if($route_point_item_type == 'destination'){
                $destination_list->push($route_point_item);
            }


        }

        //$carrier = Carrier::first();

        $inner_radius = 0;

        $outer_radius = 10;

        $destination_point = $destination_list->last();

        $latitude = $destination_point['latitude'];
        $longitude = $destination_point['longitude'];

        $carriers = $this->carrierLocationService->findNearestCarriers($latitude, $longitude);

        if(count($carriers)==0){
            return $this->sendError('Désolé, aucun carrier à proximité trouvé', 400);
        }

        $carrier = $carriers->first();

        $source_point = [
            "latitude" => $carrier->location_latitude,
            "longitude" =>  $carrier->location_longitude,
        ];


        $result = GoogleMapsAPIUtils::getDistance([
            $source_point['latitude'],
            $source_point['longitude']
        ],[
            $destination_point['latitude'],
            $destination_point['longitude']
        ]);


        $current_distance = 0;
        $distance = "";

        if(array_key_exists('distance',$result)){
            $result_distance = $result['distance']; //array
            $result_distance_value = $result_distance['value']; //meters
            $current_distance = $result_distance_value/1000; //kilometers
            $current_distance = $current_distance;
            $distance = $current_distance." km";

        }

        $duration = "";

        if(array_key_exists('duration',$result)){
            $result_duration = $result['duration']; //array
            $duration = $result_duration['text'];
        }

        //dd($current_distance);


        $customer = auth('api-customers')->user();
        $commercialDiscount = $this->getCommercialDiscount($customer);
        $amount = PricingUtils::transport($current_distance, $delivery_type_code);

        return $this->sendResponse([
            'carrier_id' => $carrier->id,
            'amount' => $amount,
            'amount_with_discount' => max(0, $amount - $commercialDiscount['discount']),
            'discount' => $commercialDiscount['discount'],
            'has_commercial_discount' => $commercialDiscount['has_commercial_discount'],
            'distance' => $distance,
            'delivery_type' => $delivery_type_code
        ], 'Order saved successfully');


   }

   public function estimateDeliveryPriceGravier(Request $request){

    /**
     *
      {
        "service_slug":"agregats-construction",
        "meta_data":{
            "product_type_slug":"gravier-515-petit-grain",
            "product_slug":"gravier",
            "delivery_type_code":"EXPRESS"
        },
        "quantity":3,
        "route_points":[
            {
                "address_name":"Pharmacie Sainte Monique du plateau dokui, Abidjan, Côte d'ivoire",
                "latitude":5.3994128,
                "longitude":-3.9999536,
                "type":"destination",
                "parcel_details":"",
                "contact_fullname": "string",
                "contact_phone":"string",
                "contact_email":""
            }
        ]
      }
     *
     */



    try{
        if(!array_key_exists('meta_data', $request->all())){

            return $this->sendError('meta_data is required', 400);
        }

        if(!array_key_exists('quantity', $request->all())){

            return $this->sendError('quantity is required', 400);
        }

        if(!array_key_exists('route_points', $request->all())){

            return $this->sendError('route_points is required', 400);
        }


        $quantity = $request->input('quantity');

        $meta_data = $request->input('meta_data');

        $route_points = $request->input('route_points');

        if(!is_array($meta_data)){
            $meta_data = (array) $meta_data;
        }

        if(!is_array($route_points)){
            $route_points = (array) $route_points;
        }

        if(!array_key_exists('product_type_slug',$meta_data)){

            return $this->sendError('product_type_slug is required', 400);
        }

        if(!array_key_exists('product_slug',$meta_data)){

            return $this->sendError('product_slug is required', 400);
        }

        if(!array_key_exists('delivery_type_code',$meta_data)){

            return $this->sendError('delivery_type_code is required', 400);
        }

        $delivery_type_code = $meta_data['delivery_type_code'];

        $source_list = collect([]);
        $destination_list = collect([]);

        foreach ($route_points as $route_point_item){
            if(!is_array($route_point_item)){
                $route_point_item = (array)$route_point_item;
            }

            $route_point_item_type = array_key_exists('type', $route_point_item)?$route_point_item['type']:null;

            if($route_point_item_type == 'source'){
                $source_list->push($route_point_item);
            }

            if($route_point_item_type == 'destination'){
                $destination_list->push($route_point_item);
            }

        }

        $inner_radius = 0;

        $outer_radius = 100;

        $destination_point = $destination_list->last();

        $latitude = $destination_point['latitude'];
        $longitude = $destination_point['longitude'];

        $carriers = $this->carrierLocationService->findNearestCarriersWithProduct($latitude, $longitude, $meta_data['product_type_slug']);

        if(count($carriers)==0){
            return $this->sendError('Désolé, aucune carrière à proximité trouvé', 400);
        }

        $carrier = $carriers->first();

        $source_point = [
            "latitude" => $carrier->location_latitude,
            "longitude" =>  $carrier->location_longitude,
        ];


        $result = GoogleMapsAPIUtils::getDistance([
            $source_point['latitude'],
            $source_point['longitude']
        ],[
            $destination_point['latitude'],
            $destination_point['longitude']

        ]);


        $current_distance = 0;
        $distance= "";

        if(array_key_exists('distance',$result)){
            $result_distance = $result['distance']; //array
            $result_distance_value = $result_distance['value']; //meters
            $current_distance = $result_distance_value/1000; //kilometers
            $current_distance = intval($current_distance);
            $distance = $result_distance['text'];

        }

        $duration = "";

        if(array_key_exists('duration',$result)){
            $result_duration = $result['duration']; //array
            $duration = $result_duration['text'];
        }



        $customer = auth('api-customers')->user();
        $commercialDiscount = $this->getCommercialDiscount($customer);
        $amount = PricingUtils::transportGravier($current_distance, $quantity, $delivery_type_code);

        return $this->sendResponse([
            'carrier' => $carrier,
            'amount' => $amount,
            'amount_with_discount' => max(0, $amount - $commercialDiscount['discount']),
            'discount' => $commercialDiscount['discount'],
            'has_commercial_discount' => $commercialDiscount['has_commercial_discount'],
            'distance' => $distance,
            'duration' => $duration,
            'delivery_type' => $delivery_type_code
        ], 'Order saved successfully');

    }catch (\Exception $e){
        return $this->sendError($e->getMessage(), 400);
    }

   }

   public function estimateDeliveryPriceSable(Request $request){

    /**
     *
      {
        "service_slug":"agregats-construction",
        "meta_data":{
            "product_type_slug":"gravier-515-petit-grain",
            "product_slug":"gravier",
            "delivery_type_code":"EXPRESS"
        },
        "quantity":3,
        "route_points":[
            {
                "address_name":"Pharmacie Sainte Monique du plateau dokui, Abidjan, Côte d'ivoire",
                "latitude":5.3994128,
                "longitude":-3.9999536,
                "type":"destination",
                "parcel_details":"",
                "contact_fullname": "string",
                "contact_phone":"string",
                "contact_email":""
            }
        ]
      }
     *
     */


    try {
    
        if(!array_key_exists('meta_data', $request->all())){

            return $this->sendError('meta_data is required', 400);
        }

        if(!array_key_exists('quantity', $request->all())){

            return $this->sendError('quantity is required', 400);
        }

        if(!array_key_exists('route_points', $request->all())){

            return $this->sendError('route_points is required', 400);
        }

        $meta_data = $request->input('meta_data');

        $route_points = $request->input('route_points');

        if(!is_array($meta_data)){
            $meta_data = (array) $meta_data;
        }

        if(!is_array($route_points)){
            $route_points = (array) $route_points;
        }

        if(!array_key_exists('product_type_slug',$meta_data)){

            return $this->sendError('product_type_slug is required', 400);
        }

        if(!array_key_exists('product_slug',$meta_data)){

            return $this->sendError('product_slug is required', 400);
        }

        if(!array_key_exists('delivery_type_code',$meta_data)){

            return $this->sendError('delivery_type_code is required', 400);
        }

        $delivery_type_code = $meta_data['delivery_type_code'];

        $source_list = collect([]);
        $destination_list = collect([]);

        foreach ($route_points as $route_point_item){
            if(!is_array($route_point_item)){
                $route_point_item = (array)$route_point_item;
            }

            $route_point_item_type = array_key_exists('type', $route_point_item)?$route_point_item['type']:null;

            if($route_point_item_type == 'source'){
                $source_list->push($route_point_item);
            }

            if($route_point_item_type == 'destination'){
                $destination_list->push($route_point_item);
            }


        }

        $inner_radius = 0;

        $outer_radius = 100;

        $destination_point = $destination_list->last();

        $latitude = $destination_point['latitude'];
        $longitude = $destination_point['longitude'];

        $carriers = $this->carrierLocationService->findNearestCarriersWithProduct($latitude, $longitude, $meta_data['product_type_slug']);

        if(count($carriers)==0){
            return $this->sendError('Désolé, aucune carrière à proximité trouvé', 400);
        }

        $carrier = $carriers->first();

        $source_point = [
            "latitude" => $carrier->location_latitude,
            "longitude" =>  $carrier->location_longitude,
        ];


        $result = GoogleMapsAPIUtils::getDistance([
            $source_point['latitude'],
            $source_point['longitude']
        ],[
            $destination_point['latitude'],
            $destination_point['longitude']

        ]);


        $distance = "";
        $current_distance = 0; // Initialiser la variable

        if(array_key_exists('distance',$result)){
            $result_distance = $result['distance']; //array
            $result_distance_value = $result_distance['value']; //meters
            $current_distance = $result_distance_value/1000; //kilometers
            $formatted_distance = number_format($current_distance);
            $distance = $formatted_distance." km";

        }

        $duration = "";

        if(array_key_exists('duration',$result)){
            $result_duration = $result['duration']; //array
            $duration = $result_duration['text'];
        }


        $customer = auth('api-customers')->user();
        $commercialDiscount = $this->getCommercialDiscount($customer);
        $amount = PricingUtils::transportSable($current_distance, $delivery_type_code);

        return $this->sendResponse([
            'carrier' => $carrier,
            'amount' => $amount,
            'amount_with_discount' => max(0, $amount - $commercialDiscount['discount']),
            'discount' => $commercialDiscount['discount'],
            'has_commercial_discount' => $commercialDiscount['has_commercial_discount'],
            'distance' => $distance,
            'duration' => $duration,
            'delivery_type' => $delivery_type_code
        ], 'Order saved successfully');

    } catch (\Throwable $th) {
        return $this->sendError($th->getMessage(), 400);
    }


   }

  public function cancel($id, Request $request){

    $customer = auth('api-customers')->user();

    /** @var Order $order */
    $order = $this->orderRepository->find($id);

    if (empty($order)) {
        return $this->sendError('Order not found');
    }

    if($order->driver_id != null && $order->started){
        return $this->sendError('Vous ne pouvez pas annuler cette commande car elle a commencé', 400);
    }

    $input['status'] = Order::CANCELLED;

    $order->is_completed = true;
    $order->completion_time = now();
    $order->is_waiting = false;
    $order->is_draft = false;
    $order->is_successful = false;

    $order->update($input);

    // Register order history
    $order->newOrderHistory(Order::CANCELLED, $customer->table, $customer->id);

    //Get OrderInvitations for this order and update is_waiting_acceptation to false
    $orderInvitations = \App\Models\OrderInvitation::where([
        'order_id' => $order->id,
        "is_waiting_acceptation" => true
    ])->get();

    //Update each OrderInvitation
    foreach($orderInvitations as $orderInvitation){
        $orderInvitation->is_waiting_acceptation = false;
        $orderInvitation->update();
    }

    return $this->sendResponse($order->toArray(), 'Order updated successfully');

  }

  public function rating($id, Request $request){

    /** @var Order $order */
    $order = $this->orderRepository->find($id);

    if (empty($order)) {
        return $this->sendError('Order not found');
    }

    $input = $request->all();

    if(!array_key_exists('rating', $input)){
        return $this->sendError('Rating is required', 400);
    }
    if(!is_numeric($input['rating']) || intval($input['rating']) < 1 || intval($input['rating']) > 5){
        return $this->sendError('Rating must be a number between 1 and 5', 400);
    }

    $order->rating = intval($input['rating']);
    $order->rating_note = $input['note']??"";
    $order->update();

    //Update the driver rating
    if($order->driver_id != null){
        $driver = Driver::find($order->driver_id);
        if($driver){
            $driver->rate = Order::where('driver_id', $driver->id)
                ->whereNotNull('rating')
                ->avg('rating');
            $driver->save();
        }
    }

    return $this->sendResponse($order->toArray(), 'Order rating updated successfully');

  }

    public function getOrderHistory($id, Request $request){

        /** @var Order $order */
        $order = $this->orderRepository->find($id);

        if (empty($order)) {
            return $this->sendError('Order not found');
        }

        $orderHistories = $order->orderHistories;

        return $this->sendResponse($orderHistories->toArray(), 'Order history retrieved successfully');

    }   


}
