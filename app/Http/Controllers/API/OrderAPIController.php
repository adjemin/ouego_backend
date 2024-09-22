<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\API\CreateOrderAPIRequest;
use App\Http\Requests\API\UpdateOrderAPIRequest;
use App\Models\Order;
use App\Models\OrderInvitation;
use App\Models\OrderItem;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\TypeEngin;
use App\Models\TypeEnginModel;
use App\Models\RoutePoint;
use App\Models\Invoice;
use App\Models\Driver;
use App\Models\Carrier;
use App\Models\DriverNotification;
use App\Utilities\DriverNotificationsUtils;
use App\Repositories\OrderRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\AppBaseController;
use Carbon\Carbon;
use App\Utilities\PricingUtils;
use App\Utilities\GoogleMapsAPIUtils;
use Grimzy\LaravelMysqlSpatial\Types\Point;
use App\Services\DriverAssignmentService;

/**
 * Class OrderAPIController
 */
class OrderAPIController extends AppBaseController
{
    private OrderRepository $orderRepository;

    private DriverAssignmentService $driverAssignmentService;

    public function __construct(OrderRepository $orderRepo, DriverAssignmentService $driverAssignmentService )
    {
        $this->orderRepository = $orderRepo;
        $this->driverAssignmentService = $driverAssignmentService;
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


        $order = Order::create([
            "reference" => Order::generateReference(),
            "customer_id" => $customer->id,
            "status" => Order::INITIATED,
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
            'payment_method_code' => 'cash',
            'delivery_type_code'=> $request->input('payment_method_code'),
            'is_location' => null,
            'is_product' => null,
            'is_ride' => null
        ]);


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

                $delivery_fees = $this->getDeliveryFees((array)$item['route_points']);

                $total_amount = $delivery_fees;

                $commission_min = doubleval(Setting::get('COMMISSION_COURSE_MIN'));
                $commission = doubleval(Setting::get('COMMISSION_COURSE'))/100;

                $commission_min = 0;
                $commission = 0;


                $service_due = $total_amount * $commission;

                $service_due = $total_amount * $commission;
                if($service_due < $commission_min){
                    $service_due = $commission_min;
                }

                $driver_due = $total_amount - $service_due;


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
                    'total_amount' => $delivery_fees,
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

                $delivery_price = intval($item['delivery_price']);
                $order->delivery_price =  $delivery_price;
                $order->save();

                $productType = ProductType::where(['slug' => $meta_data['product_type_slug']])->first();

                $product = Product::where(['id' => $productType->product_id])->first();

                $carrier =  Carrier::where(['id' => $item['carrier_id']])->first();


                $quantity = intval($item['quantity']);

                $order_price = 0;

                $delivery_price = array_key_exists('delivery_price', $item)?$item['delivery_price']:0;

                $total_amount = 0;
                $unit_price = 0;

                if($product->slug == "gravier"){
                    $unit_price = doubleval($productType->price);

                    $order_price = $quantity * $unit_price;
                }


                if($product->slug == "sable" && array_key_exists('pricing', $meta_data)){
                    $pricing = $meta_data['pricing'];

                    if(!is_array($pricing)){
                        $pricing = (array) $meta_data['pricing'];
                    }
                    $unit_price = $pricing['price'];

                    $order_price = $pricing['price'];

                    $quantity = $pricing['roues'];
                }


                $total_amount = $order_price + $delivery_price;

                $commission_min = 0;
                $commission = 0;

                if($product->slug == "gravier"){
                   // $commission_min = doubleval(Setting::get('GRAVIER_COMMISSION_OUEGO_MIN'));
                    $commission = doubleval(Setting::get('GRAVIER_COMMISSION_OUEGO'));
                }

                if($product->slug == "sable"){
                    //$commission_min = doubleval(Setting::get('SABLE_COMMISSION_OUEGO_MIN'));
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
                    'total_amount' => $total_amount,
                    'service_due' => $service_due,
                    'driver_due' => $driver_due,
                    'currency' => $currency,
                    'carrier_id' => $item['carrier_id']
                ]);

                $order->service_slug = $service->slug;
                $order->save();

                //Créer une RoutePoint avec carrier_id
                RoutePoint::create([
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

                $total_amount = $order_price + $delivery_price;

                $currency = $typeEnginModel->currency_code;

                //$commission_min = doubleval(Setting::get('SABLE_COMMISSION_OUEGO_MIN'));
                //$commission = doubleval(Setting::get('SABLE_COMMISSION_OUEGO'))/100;

                $commission_min = 0;
                $commission = 0;


                $service_due = $total_amount * $commission;

                $service_due = $total_amount * $commission;
                if($service_due < $commission_min){
                    $service_due = $commission_min;
                }

                $driver_due = $total_amount - $service_due;


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
                        'apartment' => array_key_exists('apartment', $route_point)?$route_point['apartment']:null
                    ]);

                }

            }

        }

        $order_items = OrderItem::where('order_id', $order->id)->get();

        $order_price = 0;
        $delivery_price = 0;
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
            }

            if($order_item->service_slug == Service::AGREGATS_CONSTRUCTION){
                $order->is_product = true;
                $order->save();

                $order_price = $order_price + $order_item->order_price;
                $delivery_price = $delivery_price + $order_item->delivery_price;
            }

            if($order_item->service_slug == Service::LOCATION){
                $order->is_location = true;
                $order->save();

                $order_price = $order_price + $order_item->order_price;
                $delivery_price = $delivery_price + $order_item->delivery_price;
            }

        }

        $order->order_price = $order_price;
        $order->delivery_price = $delivery_price;
        $order->save();

        $subtotal = $order->order_price;
        $tax = 0;
        $fees_delivery = $order->delivery_price;
        $total = $subtotal + $fees_delivery;
        $discount = 0;


        $invoice = Invoice::create([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'reference' => Invoice::generateID("ORDER", $order->id, $customer->id),
            'subtotal' => $subtotal,
            'tax' => $tax,
            'fees_delivery' => $fees_delivery,
            'total' => $total,
            'status' => Invoice::UNPAID,
            'is_paid_by_customer' => false,
            'is_paid_by_delivery_service' => false,
            'currency_slug' => 'XOF',
            'driver_due' => $driver_due,
            'service_due' => $service_due,
            'discount' => $discount,
            'coupon' => null
        ]);


        /** @var Order $order */
        $order = $this->orderRepository->find($order->id);


        return $this->sendResponse($order->toArray(), 'Order saved successfully');
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

        $orders = Order::where('customer_id', $customer->id)->orderBy('created_at', 'desc')->get();

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
                    $current_distance = intval($current_distance);

                }
                $duration = "";

                if(array_key_exists('duration',$result)){
                    $result_duration = $result['duration']; //array
                    $duration = $result_duration['text'];
                }

                //$current_distance = 418;

                $amount = PricingUtils::transportCourse($current_distance, $typeEnginModel);


                return $this->sendResponse([
                    "distance" => $current_distance,
                    "duration" => $duration,
                    "amount" => $amount
                ], 'Order saved successfully');


    }

    public function getDeliveryFees(array $route_points){

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
            $current_distance = intval($current_distance);

        }


        return PricingUtils::transport($current_distance);

    }

    public function confirm($id, Request $request){

        /** @var Order $order */
        $order = $this->orderRepository->find($id);

        if (empty($order)) {
            return $this->sendError('Order not found');
        }

        $input['is_draft'] = false;
        $input['order_date'] = now();
        $input['status'] = Order::PERFORMER_LOOKUP;

         $this->assign($order);

        return $this->sendResponse($order->toArray(), 'Order updated successfully');

    }

    public function assign($order){

        $inner_radius = 0;

        $outer_radius = 20;

        $route_point = RoutePoint::where([
            'order_id' => $order->id,
            'type' => 'source'
        ])->first();


        if($route_point != null){

            //$driver = Driver::where('id', 4)->first();

            //$driver->last_location  = [$driver->last_location_latitude, $driver->last_location_longitude];
            //$driver->save();
            //$all = [$driver];
            $all = Driver::all();
            foreach($all as $driver){
                $driver->last_location  = [$driver->last_location_latitude, $driver->last_location_longitude];
                $driver->save();
            }

           // dd(["type"=> "source", "location" => [$route_point->latitude,$route_point->longitude] ]);
            //$distance= 20000;
            //$all = $this->driverAssignmentService->assignNearestDriver($route_point->latitude, $route_point->longitude, $distance);
            //dd($all);

            /*$drivers = $all->where([
                'is_active' => true,
                'is_available' => true])
                ->whereJsonContains('services', $order->service_slug)
                ->orderBy('distance', 'ASC')
                ->get();*/

                //dd($drivers);


            $drivers = [
                $drivers->first()
            ];


            foreach ($drivers as $driver){


                    if($driver != null){
                        $orderInvitation = OrderInvitation::where([
                            'driver_id' => $driver->id,
                            'order_id' => $order->id,
                        ])->first();

                        if($orderInvitation == null){
                            $orderInvitation = OrderInvitation::create([
                                'driver_id' => $driver->id,
                                'order_id' => $order->id,
                                'is_waiting_acceptation' => true,
                                'acceptation_time' => null,
                                'rejection_time' => null,
                                'latitude' => null,
                                'longitude' => null
                            ]);

                            //Push Notification
                            $driverNotification = DriverNotification::create([
                                'driver_id' => $driver->id,
                                'title' => 'Course #'.$order->id." vous a été affectée",
                                'subtitle' => "Acceptez ou Refusez la course",
                                'data_id' => $orderInvitation->id,
                                'type' => $orderInvitation->table,
                                'is_read' => false,
                                'is_received' => false,
                                'meta_data' => null
                            ]);
                            DriverNotificationsUtils::notify($driverNotification);
                        }

                    }


            }
        }

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
                    $this->assign($order);
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

        $all = Carrier::geofence($latitude, $longitude, $inner_radius, $outer_radius);

        $carriers = $all->where([
            'is_active' => true])/*->whereJsonContains('services', $order->service_slug)*/->get();

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
            $current_distance = intval($current_distance);
            $distance = $result_distance['text'];

        }

        $duration = "";

        if(array_key_exists('duration',$result)){
            $result_duration = $result['duration']; //array
            $duration = $result_duration['text'];
        }

        //dd($current_distance);


        return $this->sendResponse([
            'carrier_id' => $carrier->id,
            'amount' => PricingUtils::transport($current_distance),
            'distance' => $distance
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

    $all = Carrier::geofence($latitude, $longitude, $inner_radius, $outer_radius);

    $carriers = $all->where(['is_active' => true])
        ->whereJsonContains('products', $meta_data['product_slug'])
        ->orderBy('distance', 'ASC')
        ->get();

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

    //dd($current_distance);

  //  $current_distance = 55;


    return $this->sendResponse([
        'carrier_id' => $carrier->id,
        'amount' => PricingUtils::transportGravier($current_distance, $quantity),
        'distance' => $distance,
        'duration' => $duration,
    ], 'Order saved successfully');


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

    $outer_radius = 100;

    $destination_point = $destination_list->last();

    $latitude = $destination_point['latitude'];
    $longitude = $destination_point['longitude'];

    $all = Carrier::geofence($latitude, $longitude, $inner_radius, $outer_radius);

    $carriers = $all->where(['is_active' => true])
    ->whereJsonContains('products', $meta_data['product_slug'])
    ->orderBy('distance', 'ASC')
    ->get();

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
        $current_distance = intval($current_distance);
        $distance = $result_distance['text'];

    }

    if(array_key_exists('duration',$result)){
        $result_duration = $result['duration']; //array
        $result_duration_value = $result_duration['value']; //meters
        $current_duration = $result_duration_value/1000; //kilometers
        $current_distance = intval($current_distance);

    }

    //dd($current_distance);

    //$current_distance  = 10;

    $duration = "";

    if(array_key_exists('duration',$result)){
        $result_duration = $result['duration']; //array
        $duration = $result_duration['text'];
    }


    return $this->sendResponse([
        'carrier_id' => $carrier->id,
        'amount' => PricingUtils::transportSable($current_distance),
        'distance' => $distance,
        'duration' => $duration
    ], 'Order saved successfully');


   }

}
