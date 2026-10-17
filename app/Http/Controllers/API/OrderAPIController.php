<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\API\CreateOrderAPIRequest;
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
use App\Models\DeliveryType;
use App\Repositories\OrderRepository;   
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\AppBaseController;
use Carbon\Carbon;
use App\Utilities\PricingUtils;
use App\Utilities\GoogleMapsAPIUtils;
use App\Services\DriverAssignmentService;
use App\Services\CarrierLocationService;
use App\Jobs\SendSms;
use Illuminate\Support\Facades\DB;
/**
 * Class OrderAPIController
 */
class OrderAPIController extends AppBaseController
{
    private OrderRepository $orderRepository;
    private CarrierLocationService $carrierLocationService;

    public function __construct(
        OrderRepository $orderRepo, 
        CarrierLocationService $carrierLocationService
    )
    {
        $this->orderRepository = $orderRepo;
        $this->carrierLocationService = $carrierLocationService;
    }

    /**
     * Commande du client connecté, ou null si elle n'existe pas ou appartient à un autre client.
     */
    private function findCustomerOrder($id): ?Order
    {
        $customer = auth('api-customers')->user();

        /** @var Order|null $order */
        $order = $this->orderRepository->find($id);

        if (empty($order) || empty($customer) || $order->customer_id !== $customer->id) {
            return null;
        }

        return $order;
    }

    /**
     * Le client propriétaire, le chauffeur assigné et les chauffeurs invités peuvent consulter la commande.
     */
    private function canViewOrder(Order $order): bool
    {
        $customer = auth('api-customers')->user();
        if ($customer) {
            return $order->customer_id === $customer->id;
        }

        $driver = auth('api-drivers')->user();
        if ($driver) {
            return $order->driver_id === $driver->id
                || OrderInvitation::where('order_id', $order->id)->where('driver_id', $driver->id)->exists();
        }

        return false;
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
     * Store a newly created Order in storage.
     * POST /orders
     */
    public function store(CreateOrderAPIRequest $request): JsonResponse
    {
        $customer = auth('api-customers')->user();

        $input = $request->all();

        if(empty($request->input('payment_method_code'))){
            return $this->sendError('Le champ payment_method_code est obligatoire', 400);
        }

        if(empty($request->input('items'))){
            return $this->sendError('Le champ items est obligatoire', 400);
        }


        $items = (array) $request->input('items');

        // Verifier la disponibilité de la course le type de livraison
        if(count($items)){
            $meta_data = (array) $items[0]['meta_data'];
            $delivery_type_code = array_key_exists('delivery_type_code', $meta_data)?$meta_data['delivery_type_code']:null;

            if($unavailabilityMessage = DeliveryType::unavailabilityMessage($delivery_type_code)){
                return $this->sendError($unavailabilityMessage);
            }
        }

        DB::beginTransaction();

        try {
            return $this->persistOrder($request, $customer, $items);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Crée la commande, ses articles, ses points de passage et sa facture dans la transaction ouverte par store().
     * Un champ invalide annule la transaction et renvoie une erreur.
     */
    private function persistOrder(CreateOrderAPIRequest $request, $customer, array $items): JsonResponse
    {
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
                DB::rollBack();
                return $this->sendError('Le champ service_slug est obligatoire', 400);
            }

            $service = Service::where('slug', $item["service_slug"])->first();

            if($service == null){
                DB::rollBack();
                return $this->sendError('Service introuvable', 400);
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
                    DB::rollBack();
                    return $this->sendError('Le champ meta_data est obligatoire', 400);
                }

                if(!array_key_exists('route_points',$item)){
                    DB::rollBack();
                    return $this->sendError('Le champ route_points est obligatoire', 400);
                }

                $meta_data = $item['meta_data'];
                if(!is_array($meta_data)){
                    $meta_data = (array) $item['meta_data'];
                }

                if(!array_key_exists('type_engin_slug',$meta_data)){
                    DB::rollBack();
                    return $this->sendError('Le champ type_engin_slug est obligatoire', 400);
                }

                if(!array_key_exists('engin_model',$meta_data)){
                    DB::rollBack();
                    return $this->sendError('Le champ engin_model est obligatoire', 400);
                }

                if(!array_key_exists('delivery_type_code',$meta_data)){
                    DB::rollBack();
                    return $this->sendError('Le champ delivery_type_code est obligatoire', 400);
                }

                $delivery_type_code = array_key_exists('delivery_type_code', $meta_data)?$meta_data['delivery_type_code']:'EXPRESS';

                $delivery_fees = array_key_exists('delivery_fees', $meta_data)?$meta_data['delivery_fees']:0;

                if($delivery_fees == 0){
                    $delivery_fees = $this->courseDeliveryFees((array)$item['route_points'], $meta_data['engin_model'], $delivery_type_code);

                    if($delivery_fees === null){
                        DB::rollBack();
                        return $this->sendError("Modèle d'engin introuvable", 400);
                    }
                }

                $manutention_pricing = array_key_exists('manutention_pricing', $meta_data)?$meta_data['manutention_pricing']:0; 

                $order->delivery_type_code = $delivery_type_code;

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
                    DB::rollBack();
                    return $this->sendError('Le champ meta_data est obligatoire', 400);
                }

                if(!array_key_exists('route_points',$item)){
                    DB::rollBack();
                    return $this->sendError('Le champ route_points est obligatoire', 400);
                }

                if(!array_key_exists('delivery_price',$item)){
                    DB::rollBack();
                    return $this->sendError('Le champ delivery_price est obligatoire', 400);
                }

                if(!array_key_exists('carrier_id',$item)){
                    DB::rollBack();
                    return $this->sendError('Le champ carrier_id est obligatoire', 400);
                }

                $meta_data = $item['meta_data'];
                if(!is_array($meta_data)){
                    $meta_data = (array) $item['meta_data'];
                }

                if(!array_key_exists('product_type_slug',$meta_data)){
                    DB::rollBack();
                    return $this->sendError('Le champ product_type_slug est obligatoire', 400);
                }

                if(!array_key_exists('product_slug',$meta_data)){
                    DB::rollBack();
                    return $this->sendError('Le champ product_slug est obligatoire', 400);
                }

                if(!array_key_exists('delivery_type_code',$meta_data)){
                    DB::rollBack();
                    return $this->sendError('Le champ delivery_type_code est obligatoire', 400);
                }

                if(!array_key_exists('quantity',$item)){
                    DB::rollBack();
                    return $this->sendError('Le champ quantity est obligatoire', 400);
                }

                if(!array_key_exists('product_type_slug', $meta_data)){
                    DB::rollBack();
                    return $this->sendError('Le champ product_type_slug est obligatoire', 400);
                }

                if(!array_key_exists('product_slug', $meta_data)){
                    DB::rollBack();
                    return $this->sendError('Le champ product_slug est obligatoire', 400);
                }

                if(!array_key_exists('delivery_type_code', $meta_data)){
                    DB::rollBack();
                    return $this->sendError('Le champ delivery_type_code est obligatoire', 400);
                }


                $order->delivery_type_code = $meta_data['delivery_type_code'];

                $delivery_price = intval($item['delivery_price']);
                $order->delivery_price =  $delivery_price;

                $productType = ProductType::where(['slug' => $meta_data['product_type_slug']])->first();
                if(empty($productType)) {
                    DB::rollBack();
                    return $this->sendError('Type de produit introuvable', 400);
                }

                $product = Product::where(['id' => $productType->product_id])->first();
                if(empty($product)) {
                    DB::rollBack();
                    return $this->sendError('Produit introuvable', 400);
                }

                $carrier =  Carrier::where(['id' => $item['carrier_id']])->first();
                if(empty($carrier)) {
                    DB::rollBack();
                    return $this->sendError('Carrière introuvable', 400);
                }

                if($product->slug == Product::SABLE_SLUG && !array_key_exists('pricing', $meta_data)){
                    DB::rollBack();
                    return $this->sendError('Le champ pricing est obligatoire pour le sable', 400);
                }

                if($product->slug == Product::SABLE_SLUG){
                    $pricing = $meta_data['pricing'];
                    if(!is_array($pricing)){
                        $pricing = (array) $pricing;
                    }

                    if(
                        !array_key_exists('price', $pricing) ||
                        !array_key_exists('roues', $pricing) ||
                        !is_numeric($pricing['price']) ||
                        !is_numeric($pricing['roues']) ||
                        doubleval($pricing['price']) <= 0 ||
                        intval($pricing['roues']) <= 0
                    ){
                        DB::rollBack();
                        return $this->sendError('Le champ pricing est invalide pour le sable', 400);
                    }
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

                if($product->slug == Product::CIMENT_SLUG){
                    $unit_price = doubleval($productType->price);

                    $order_price = $quantity * $unit_price;
                }


                if($product->slug == Product::SABLE_SLUG && array_key_exists('pricing', $meta_data)){
                    $pricing = $meta_data['pricing'];

                    if(!is_array($pricing)){
                        $pricing = (array) $meta_data['pricing'];
                    }
                    $unit_price = doubleval($pricing['price']);

                    $order_price = doubleval($pricing['price']);

                    $quantity = intval($pricing['roues']);
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

                if($product->slug == Product::CIMENT_SLUG){
                    $commission_min = doubleval(Setting::get('CIMENT_COMMISSION_OUEGO_MIN'));
                    $commission = doubleval(Setting::get('CIMENT_COMMISSION_OUEGO'));
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
                    DB::rollBack();
                    return $this->sendError('Le champ meta_data est obligatoire', 400);
                }

                if(!array_key_exists('route_points',$item)){
                    DB::rollBack();
                    return $this->sendError('Le champ route_points est obligatoire', 400);
                }

                $meta_data = $item['meta_data'];
                if(!is_array($meta_data)){
                    $meta_data = (array) $item['meta_data'];
                }

                if(!array_key_exists('type_engin_slug',$meta_data)){
                    DB::rollBack();
                    return $this->sendError('Le champ type_engin_slug est obligatoire', 400);
                }

                if(!array_key_exists('engin_model',$meta_data)){
                    DB::rollBack();
                    return $this->sendError('Le champ engin_model est obligatoire', 400);
                }

                if(!array_key_exists('quantity',$item)){
                    DB::rollBack();
                    return $this->sendError('Le champ quantity est obligatoire', 400);
                }

                if(!array_key_exists('location_start_date',$item)){
                    DB::rollBack();
                    return $this->sendError('Le champ location_start_date est obligatoire', 400);
                }

                if(!array_key_exists('location_end_date',$item)){
                    DB::rollBack();
                    return $this->sendError('Le champ location_end_date est obligatoire', 400);
                }

                $location_start_date = Carbon::parse($item["location_start_date"]);
                $location_end_date = Carbon::parse($item["location_end_date"]);

                $location_shift_type = array_key_exists('location_shift_type', $item) ? $item['location_shift_type'] : 'day';
                if (!in_array($location_shift_type, ['day', 'night', 'double'])) {
                    $location_shift_type = 'day';
                }
                $coefficient_shift = ($location_shift_type === 'double') ? 2 : 1;

                $typeEngin = TypeEngin::where(['slug' => $meta_data['type_engin_slug']])->first();
                $typeEnginModel = TypeEnginModel::where(['slug' => $meta_data['engin_model']])->first();

                if($typeEngin == null){
                    DB::rollBack();
                    return $this->sendError("Type d'engin introuvable", 400);
                }

                if($typeEnginModel == null){
                    DB::rollBack();
                    return $this->sendError("Modèle d'engin introuvable", 400);
                }

                $quantity = max(1, $location_start_date->diffInDays($location_end_date));

                $unit_price = doubleval($typeEnginModel->day_location_price);

                $order_price = $quantity * $unit_price * $coefficient_shift;

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
                    'location_end_date' => $item["location_end_date"],
                    'location_shift_type' => $location_shift_type,
                ]);

                $order->service_slug = $service->slug;


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

                $order_price = $order_price + $order_item->order_price;
                $delivery_price = $delivery_price + $order_item->delivery_price;
                $manutention_pricing = $manutention_pricing + $order_item->manutention_pricing;
            }

            if($order_item->service_slug == Service::AGREGATS_CONSTRUCTION){
                $order->is_product = true;

                $order_price = $order_price + $order_item->order_price;
                $delivery_price = $delivery_price + $order_item->delivery_price;
                $manutention_pricing = $manutention_pricing + $order_item->manutention_pricing;
            }

            if($order_item->service_slug == Service::LOCATION){
                $order->is_location = true;

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
        

        $invoice_total = $subtotal + $fees_delivery + $tax + $manutention_pricing;

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
                        SendSms::dispatch("+" . $commercial->phone, "OUEGO: Une nouvelle commande d'un client est arrivée. Votre compte a été credité de {$creditAmount} XOF. Votre nouveau solde est de {$commercial->current_balance} XOF.")->afterCommit();
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

        DB::commit();
        


        /** @var Order $order */
        $order = $this->orderRepository->find($order->id);


        return $this->sendResponse($order->toArray(), 'Commande enregistrée avec succès');
    }

    public function pay($id, Request $request){

        $order = $this->findCustomerOrder($id);

        if (empty($order)) {
            return $this->sendError('Commande introuvable');
        }

        $invoice = Invoice::where('order_id', $order->id)->first();

        if (empty($invoice)) {
            return $this->sendError('Facture introuvable');
        }

        if($invoice->status == Invoice::PAID){
            return $this->sendError('Facture déjà payée', 400);
        }

        //Create Payment
        $payment = Payment::create([
            'payment_method_code' => 'online',
            'invoice_id' => $invoice->id,
            'user_id' => $order->customer_id,
            'payment_reference' => Payment::generateReference(),
            'amount' => $invoice->total,
            'currency_code' => $invoice->currency_code ?? 'XOF',
            'status' => Payment::STATUS_PENDING,
            'is_waiting' => true,
            'is_completed' => false
        ]);

        return $this->sendResponse($payment->toArray(), 'Paiement enregistré avec succès');

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
            return $this->sendError('Commande introuvable');
        }

        if (!$this->canViewOrder($order)) {
            if (!auth('api-customers')->check() && !auth('api-drivers')->check()) {
                return $this->sendError("Jeton d'authentification introuvable", 401);
            }

            return $this->sendError('Commande introuvable');
        }

        return $this->sendResponse($order->toArray(), 'Commande récupérée avec succès');
    }

    public function getCustomerOrders(Request $request){

        $customer = auth('api-customers')->user();

        $orders = Order::where('customer_id', $customer->id)->orderBy('created_at', 'desc')->take(10)->get();

        return $this->sendResponse($orders->toArray(), 'Commandes récupérées avec succès');

    }

    public function getDriverOrders(Request $request){

        $driver = auth('api-drivers')->user();

        $orders = Order::where('driver_id', $driver->id)->orderBy('created_at', 'desc')->get();

        return $this->sendResponse($orders->toArray(), 'Commandes récupérées avec succès');

    }

    public function estimateRidePriceWithArrets(Request $request){

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
            return $this->sendError('Le champ meta_data est obligatoire', 400);
        }

        if(!array_key_exists('route_points', $request->all())){

            return $this->sendError('Le champ route_points est obligatoire', 400);
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

            return $this->sendError('Le champ type_engin_slug est obligatoire', 400);
        }

        if(!array_key_exists('engin_model',$meta_data)){

            return $this->sendError('Le champ engin_model est obligatoire', 400);
        }

        if(!array_key_exists('delivery_type_code',$meta_data)){

            return $this->sendError('Le champ delivery_type_code est obligatoire', 400);
        }

        $typeEnginModel = TypeEnginModel::where('slug', $meta_data['engin_model'])->first();

        if(empty($typeEnginModel)){
            return $this->sendError('Le champ engin_model est obligatoire', 400);
        }

        [$sources, $destinations, $stops] = $this->splitRoutePoints($route_points);

        // Distance et durée cumulées des tronçons : départ → arrêts → destination
        $points = collect([$sources->first()])->concat($stops)->push($destinations->last())->values();
        $current_distance = 0;
        $duration = 0;

        for ($i = 1; $i < $points->count(); $i++) {
            $leg = $this->routeLeg($points[$i - 1], $points[$i]);
            $current_distance = $current_distance + $leg['meters'] / 1000;
            $duration = $duration + $leg['seconds'];
        }

        $duration = intval($duration / 60) . " mins";

        $pricings = [];
        foreach ([DeliveryType::TYPE_EXPRESS, DeliveryType::TYPE_EN_JOURNEE, DeliveryType::TYPE_DE_NUIT, DeliveryType::TYPE_DE_SEMAINE] as $delivery_type_code) {
            $unavailabilityMessage = DeliveryType::unavailabilityMessage($delivery_type_code);

            $pricings[] = [
                "distance" => $current_distance,
                "duration" => $duration,
                "amount" => PricingUtils::transportCourse($current_distance, $typeEnginModel, $delivery_type_code),
                "delivery_type" => DeliveryType::findBySlug($delivery_type_code),
                "is_available" => $unavailabilityMessage === null,
                "error_message" => $unavailabilityMessage
            ];
        }

        $customer = auth('api-customers')->user();
        $commercialDiscount = $this->getCommercialDiscount($customer);

        return $this->sendResponse([
            'pricings' => $pricings,
            'discount' => $commercialDiscount['discount'],
            'has_commercial_discount' => $commercialDiscount['has_commercial_discount'],
        ], 'Commande enregistrée avec succès');
    }

    /**
     * Frais de livraison d'une course, ou null si le modèle d'engin n'existe pas.
     */
    private function courseDeliveryFees(array $route_points, string $engin_model, string $delivery_type_code): ?float
    {
        $typeEnginModel = TypeEnginModel::where('slug', $engin_model)->first();

        if (empty($typeEnginModel)) {
            return null;
        }

        [$sources, $destinations] = $this->splitRoutePoints($route_points);

        $leg = $this->routeLeg($sources->first(), $destinations->last());

        return PricingUtils::transportCourse($leg['meters'] / 1000, $typeEnginModel, $delivery_type_code);
    }

    /**
     * Sépare les points de passage par type : départs, destinations et arrêts intermédiaires.
     */
    private function splitRoutePoints(array $route_points): array
    {
        $points = collect($route_points)->map(fn ($point) => (array) $point);

        return [
            $points->where('type', 'source')->values(),
            $points->where('type', 'destination')->values(),
            $points->where('type', 'arret')->values(),
        ];
    }

    /**
     * Distance et durée routières (Google Maps) d'un point à un autre.
     */
    private function routeLeg(array $from, array $to): array
    {
        $result = GoogleMapsAPIUtils::getDistance([$from['latitude'], $from['longitude']], [$to['latitude'], $to['longitude']]);

        return [
            'has_distance' => array_key_exists('distance', $result),
            'meters' => $result['distance']['value'] ?? 0,
            'distance_text' => $result['distance']['text'] ?? '',
            'seconds' => intval($result['duration']['value'] ?? 0),
            'duration_text' => $result['duration']['text'] ?? '',
        ];
    }

    public function confirm($id, Request $request){

        $customer = auth('api-customers')->user();

        $order = $this->findCustomerOrder($id);

        if (empty($order)) {
            return $this->sendError('Commande introuvable');
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

        return $this->sendResponse($order->toArray(), 'Commande mise à jour avec succès');
    }


    public function performDriverLookup($id, Request $request){
        $order = $this->findCustomerOrder($id);

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

        return $this->sendResponse($order->toArray(), 'Commande récupérée avec succès');

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

            return $this->sendError('Le champ meta_data est obligatoire', 400);
        }

        if(!array_key_exists('quantity', $request->all())){

            return $this->sendError('Le champ quantity est obligatoire', 400);
        }

        if(!array_key_exists('route_points', $request->all())){

            return $this->sendError('Le champ route_points est obligatoire', 400);
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

            return $this->sendError('Le champ product_type_slug est obligatoire', 400);
        }

        if(!array_key_exists('product_slug',$meta_data)){

            return $this->sendError('Le champ product_slug est obligatoire', 400);
        }

        if(!array_key_exists('delivery_type_code',$meta_data)){

            return $this->sendError('Le champ delivery_type_code est obligatoire', 400);
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
            return $this->sendError('Désolé, aucune carrière trouvée à proximité', 400);
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

        //dd($current_distance);


        $customer = auth('api-customers')->user();
        $commercialDiscount = $this->getCommercialDiscount($customer);
        $amount = PricingUtils::transport($current_distance, $delivery_type_code);
        
        $message = DeliveryType::unavailabilityMessage($delivery_type_code);
        $isAvailable = $message === null;

        return $this->sendResponse([
            'carrier_id' => $carrier->id,
            'amount' => $amount,
            'amount_with_discount' => max(0, $amount - $commercialDiscount['discount']),
            'discount' => $commercialDiscount['discount'],
            'has_commercial_discount' => $commercialDiscount['has_commercial_discount'],
            'distance' => $distance,
            'delivery_type' => $delivery_type_code,
            'is_available' => $isAvailable,
            'error_message' => $message
        ], 'Commande enregistrée avec succès');


   }

   public function estimateDeliveryPriceGravier(Request $request){
        return $this->estimateAggregatDelivery($request, fn ($distance, $quantity, $deliveryType) => PricingUtils::transportGravier($distance, $quantity, $deliveryType));
   }

   public function estimateDeliveryPriceCiment(Request $request){
        return $this->estimateAggregatDelivery($request, fn ($distance, $quantity, $deliveryType) => PricingUtils::transportCiment($distance, $quantity, $deliveryType));
   }

   public function estimateDeliveryPriceSable(Request $request){
        // Le sable est vendu au camion : la quantité est ignorée, la distance n'est pas arrondie
        return $this->estimateAggregatDelivery($request, fn ($distance, $quantity, $deliveryType) => PricingUtils::transportSable($distance, $deliveryType), false);
   }

    /**
     * Estimation de la livraison d'un agrégat depuis la carrière la plus proche qui vend le produit.
     *
     * @param callable $price fn (distance en km, quantité, type de livraison) => montant
     * @param bool $roundDistance distance arrondie au km inférieur et libellé de Google (gravier, ciment),
     *                            ou distance exacte et libellé « N km » (sable)
     */
    private function estimateAggregatDelivery(Request $request, callable $price, bool $roundDistance = true): JsonResponse
    {
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

            return $this->sendError('Le champ meta_data est obligatoire', 400);
        }

        if(!array_key_exists('quantity', $request->all())){

            return $this->sendError('Le champ quantity est obligatoire', 400);
        }

        if(!array_key_exists('route_points', $request->all())){

            return $this->sendError('Le champ route_points est obligatoire', 400);
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

            return $this->sendError('Le champ product_type_slug est obligatoire', 400);
        }

        if(!array_key_exists('product_slug',$meta_data)){

            return $this->sendError('Le champ product_slug est obligatoire', 400);
        }

        if(!array_key_exists('delivery_type_code',$meta_data)){

            return $this->sendError('Le champ delivery_type_code est obligatoire', 400);
        }

        $delivery_type_code = $meta_data['delivery_type_code'];

        [, $destinations] = $this->splitRoutePoints($route_points);
        $destination_point = $destinations->last();

        $carriers = $this->carrierLocationService->findNearestCarriersWithProduct($destination_point['latitude'], $destination_point['longitude'], $meta_data['product_type_slug']);

        if(count($carriers)==0){
            return $this->sendError('Désolé, aucune carrière trouvée à proximité', 400);
        }

        $carrier = $carriers->first();

        $leg = $this->routeLeg([
            "latitude" => $carrier->location_latitude,
            "longitude" => $carrier->location_longitude,
        ], $destination_point);

        $current_distance = $leg['meters'] / 1000;
        $distance = "";

        if ($leg['has_distance']) {
            if ($roundDistance) {
                $current_distance = intval($current_distance);
                $distance = $leg['distance_text'];
            } else {
                $distance = number_format($current_distance)." km";
            }
        }

        $customer = auth('api-customers')->user();
        $commercialDiscount = $this->getCommercialDiscount($customer);
        $amount = $price($current_distance, $quantity, $delivery_type_code);

        $unavailabilityMessage = DeliveryType::unavailabilityMessage($delivery_type_code);

        return $this->sendResponse([
            'carrier' => $carrier,
            'amount' => $amount,
            'amount_with_discount' => max(0, $amount - $commercialDiscount['discount']),
            'discount' => $commercialDiscount['discount'],
            'has_commercial_discount' => $commercialDiscount['has_commercial_discount'],
            'distance' => $distance,
            'duration' => $leg['duration_text'],
            'delivery_type' => $delivery_type_code,
            'is_available' => $unavailabilityMessage === null,
            'error_message' => $unavailabilityMessage
        ], 'Commande enregistrée avec succès');

      }catch (\Exception $e){
        return $this->sendError($e->getMessage(), 400);
      }
    }

  public function cancel($id, Request $request){

    $customer = auth('api-customers')->user();

    $order = $this->findCustomerOrder($id);

    if (empty($order)) {
        return $this->sendError('Commande introuvable');
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

    // Retirer la commande de la liste des chauffeurs invités
    \App\Models\OrderInvitation::cancelWaiting(
        \App\Models\OrderInvitation::where('order_id', $order->id),
        \App\Events\OrderInvitationCancelled::ORDER_CANCELLED
    );

    return $this->sendResponse($order->toArray(), 'Commande mise à jour avec succès');

  }

  public function rating($id, Request $request){

    $order = $this->findCustomerOrder($id);

    if (empty($order)) {
        return $this->sendError('Commande introuvable');
    }

    $input = $request->all();

    if(!array_key_exists('rating', $input)){
        return $this->sendError('Le champ rating est obligatoire', 400);
    }
    if(!is_numeric($input['rating']) || intval($input['rating']) < 1 || intval($input['rating']) > 5){
        return $this->sendError('La note doit être un nombre entre 1 et 5', 400);
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

    return $this->sendResponse($order->toArray(), 'Note de la commande mise à jour avec succès');

  }

    public function getOrderHistory($id, Request $request){

        $order = $this->findCustomerOrder($id);

        if (empty($order)) {
            return $this->sendError('Commande introuvable');
        }

        $orderHistories = $order->orderHistories;

        return $this->sendResponse($orderHistories->toArray(), 'Historique de la commande récupéré avec succès');

    }   


}