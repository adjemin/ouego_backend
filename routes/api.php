<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::prefix('v1/')->group(function () {

    Route::post('notifications/send/{id}', [App\Http\Controllers\API\DriverNotificationAPIController::class, 'submitTestNotification']);

    //OTP
    //Get OTP
    Route::post('customers/send-otp', [App\Http\Controllers\API\CustomerAPIController::class, 'sendOTP']);

    Route::post('customers/verify-otp', [App\Http\Controllers\API\CustomerAPIController::class, 'verifyOTP']);


    //Inscription par téléphone (Client)
    Route::post('customers/register', [App\Http\Controllers\API\CustomerAPIController::class, 'register']);
    Route::post('customers/login', [App\Http\Controllers\API\CustomerAPIController::class, 'login']);
    Route::post('customers/logout', [App\Http\Controllers\API\CustomerAPIController::class, 'logout'])->middleware("auth.customer:api-customers");
    Route::post('customers/refresh', [App\Http\Controllers\API\CustomerAPIController::class, 'refresh'])->middleware("auth.customer:api-customers");
    Route::put('customers/edit_profil', [App\Http\Controllers\API\CustomerAPIController::class, 'update'])->middleware("auth.customer:api-customers");
    Route::get('customers/get_profil', [App\Http\Controllers\API\CustomerAPIController::class, 'getProfil'])->middleware("auth.customer:api-customers");

    Route::post('customers/devices/create', [App\Http\Controllers\API\CustomerDeviceAPIController::class, 'store'])->middleware("auth.customer:api-customers");

    Route::get('delivery_types/list', [App\Http\Controllers\API\DeliveryTypeAPIController::class, 'index']);

    Route::get('slides/list', [App\Http\Controllers\API\SlideAPIController::class, 'index']);

    Route::get('services/list', [App\Http\Controllers\API\ServiceAPIController::class, 'index']);
    Route::post('services/create', [App\Http\Controllers\API\ServiceAPIController::class, 'store'])->middleware("auth.customer:api-customers");

    Route::get('products/list', [App\Http\Controllers\API\ProductAPIController::class, 'index']);
    Route::post('products/create', [App\Http\Controllers\API\ProductAPIController::class, 'store'])->middleware("auth.customer:api-customers");
    Route::get('products/{id}', [App\Http\Controllers\API\ProductAPIController::class, 'show'])->middleware("auth.customer:api-customers");

    Route::post('product_types/create', [App\Http\Controllers\API\ProductTypeAPIController::class, 'store'])->middleware("auth.customer:api-customers");

    Route::post('orders/create', [App\Http\Controllers\API\OrderAPIController::class, 'store'])->middleware("auth.customer:api-customers");

    //Payer une commande
    Route::put('orders/{id}/pay', [App\Http\Controllers\API\OrderAPIController::class, 'pay'])->middleware("auth.customer:api-customers");

    //Webhook
    Route::post('payments/webhook', [App\Http\Controllers\API\PaymentAPIController::class, 'webhook']);

    Route::get('payments/webhook', [App\Http\Controllers\API\PaymentAPIController::class, 'webhook']);


    //Confirmer une course (client)
    Route::put('orders/{id}/confirm', [App\Http\Controllers\API\OrderAPIController::class, 'confirm']);

    Route::put('orders/{id}/cancel', [App\Http\Controllers\API\OrderAPIController::class, 'cancel']);

    Route::put('orders/{id}/rating', [App\Http\Controllers\API\OrderAPIController::class, 'rating']);


    // Historique des commandes
    Route::get('orders/{id}/history', [App\Http\Controllers\API\OrderAPIController::class, 'getOrderHistory'])->middleware("auth.customer:api-customers");  
    

    //Détails d'une course par ID (client)
    Route::get('orders/{id}/info', [App\Http\Controllers\API\OrderAPIController::class, 'show']);

    //Rechercher d'un livreur
    Route::put('orders/{id}/perform_driver_lookup', [App\Http\Controllers\API\OrderAPIController::class, 'performDriverLookup']);

    Route::get('customers/orders/list', [App\Http\Controllers\API\OrderAPIController::class, 'getCustomerOrders'])->middleware("auth.customer:api-customers");

    Route::get('drivers/orders/list', [App\Http\Controllers\API\OrderAPIController::class, 'getDriverOrders'])->middleware("auth.driver:api-drivers");


    Route::post('orders/ride/estimate_price', [App\Http\Controllers\API\OrderAPIController::class, 'estimateRidePriceWithArrets'])->middleware("auth.customer:api-customers");

    Route::post('orders/delivery/estimate_price', [App\Http\Controllers\API\OrderAPIController::class, 'estimateDeliveryPrice'])->middleware("auth.customer:api-customers");

    Route::post('orders/delivery/gravier/estimate_price', [App\Http\Controllers\API\OrderAPIController::class, 'estimateDeliveryPriceGravier'])->middleware("auth.customer:api-customers");

    Route::post('orders/delivery/sable/estimate_price', [App\Http\Controllers\API\OrderAPIController::class, 'estimateDeliveryPriceSable'])->middleware("auth.customer:api-customers");

    Route::post('type-engins/create', [App\Http\Controllers\API\TypeEnginAPIController::class, 'store']);

    Route::get('type-engins/list', [App\Http\Controllers\API\TypeEnginAPIController::class, 'index']);

    Route::post('type-engins-models/create', [App\Http\Controllers\API\TypeEnginModelAPIController::class, 'store']);


    //Accepter ou Refuser une taches  (Livreur)
    Route::put('order_invitations/{id}/accept', [App\Http\Controllers\API\OrderInvitationAPIController::class, 'accept']);
    Route::put('order_invitations/{id}/refuse', [App\Http\Controllers\API\OrderInvitationAPIController::class, 'refuse']);

    //TODO Modifier le statut d’un ramassage ou livraison (ANNULER, DEMARRER, REUSSIR, ECHOUER ) (Livreur)
    Route::put('route_points/{id}/update_status', [App\Http\Controllers\API\RoutePointAPIController::class, 'updateStatus'])->middleware("auth.driver:api-drivers");
    
    // Détails d'un point de ramassage ou livraison (Livreur)
    Route::get('route_points/customer', [App\Http\Controllers\API\RoutePointAPIController::class, 'indexByCustomer'])->middleware("auth.customer:api-customers");


    //Inscription par téléphone Driver
    Route::post('drivers/register', [App\Http\Controllers\API\DriverAPIController::class, 'register']);
    Route::post('drivers/login', [App\Http\Controllers\API\DriverAPIController::class, 'login']);

    Route::post('drivers/send-otp', [App\Http\Controllers\API\DriverAPIController::class, 'sendOTP']);

    Route::post('drivers/verify-otp', [App\Http\Controllers\API\DriverAPIController::class, 'verifyOTP']);

    Route::post('drivers/logout', [App\Http\Controllers\API\DriverAPIController::class, 'logout'])->middleware("auth.driver:api-drivers");
    Route::post('drivers/refresh', [App\Http\Controllers\API\DriverAPIController::class, 'refresh'])->middleware("auth.driver:api-drivers");
    Route::put('drivers/edit_profil', [App\Http\Controllers\API\DriverAPIController::class, 'update'])->middleware("auth.driver:api-drivers");
    Route::get('drivers/get_profil', [App\Http\Controllers\API\DriverAPIController::class, 'getProfil'])->middleware("auth.driver:api-drivers");

    Route::post('drivers/cars/create', [App\Http\Controllers\API\DriverAPIController::class, 'createCar'])->middleware("auth.driver:api-drivers");
    Route::put('drivers/cars/{id}/update', [App\Http\Controllers\API\DriverAPIController::class, 'updateCar'])->middleware("auth.driver:api-drivers");


    Route::post('drivers/devices/create', [App\Http\Controllers\API\DriverDeviceAPIController::class, 'store'])->middleware("auth.driver:api-drivers");

    Route::get('drivers/orders_invitations/list', [App\Http\Controllers\API\OrderInvitationAPIController::class, 'index'])->middleware("auth.driver:api-drivers");


    Route::get('drivers/orders_invitations/{id}/show', [App\Http\Controllers\API\OrderInvitationAPIController::class, 'show'])->middleware("auth.driver:api-drivers");

    Route::put('drivers/orders_invitations/{id}/accept', [App\Http\Controllers\API\OrderInvitationAPIController::class, 'accept'])->middleware("auth.driver:api-drivers");
    Route::put('drivers/orders_invitations/{id}/refuse', [App\Http\Controllers\API\OrderInvitationAPIController::class, 'refuse'])->middleware("auth.driver:api-drivers");


    Route::put('drivers/availabilities/{id}/update', [App\Http\Controllers\API\DriverAPIController::class, 'updateAvailability']);

    Route::get('drivers/notifications/list', [App\Http\Controllers\API\DriverNotificationAPIController::class, 'index'])->middleware("auth.driver:api-drivers");
    Route::put('drivers/notifications/{id}/update', [App\Http\Controllers\API\DriverNotificationAPIController::class, 'update'])->middleware("auth.driver:api-drivers");

    Route::get('drivers/carriers', [App\Http\Controllers\API\CarrierAPIController::class, 'driverCarriers'])->middleware("auth.driver:api-drivers");
    Route::post('drivers/carriers/{carrierId}', [App\Http\Controllers\API\CarrierAPIController::class, 'addCarrierToDriver'])->middleware("auth.driver:api-drivers");
    Route::delete('drivers/carriers/{carrierId}', [App\Http\Controllers\API\CarrierAPIController::class, 'removeCarrierFromDriver'])->middleware("auth.driver:api-drivers");

    Route::post('drivers/transactions/balance/deposit', [App\Http\Controllers\API\DriverAPIController::class, 'depositBalance']);
    Route::post('drivers/transactions/balance/withdraw', [App\Http\Controllers\API\DriverAPIController::class, 'withdrawBalance']);
    Route::get('drivers/earnings/daily', [App\Http\Controllers\API\DriverAPIController::class, 'getDailyEarnings'])->middleware("auth.driver:api-drivers");
    Route::put('drivers/zone-base/update', [App\Http\Controllers\API\DriverAPIController::class, 'updateZoneBase'])->middleware("auth.driver:api-drivers");
    Route::get('drivers/order-invitations/pending', [App\Http\Controllers\API\DriverAPIController::class, 'getPendingOrderInvitations'])->middleware("auth.driver:api-drivers");

    Route::get('zones', [App\Http\Controllers\API\ZoneAPIController::class, 'index'])->middleware("auth.driver:api-drivers");
    Route::get('carriers', [App\Http\Controllers\API\CarrierAPIController::class, 'index'])->middleware("auth.driver:api-drivers");
    Route::get('carriers/search', [App\Http\Controllers\API\CarrierAPIController::class, 'search'])->middleware("auth.driver:api-drivers");

    Route::post('notifications/confirm-delivery', [App\Http\Controllers\API\NotificationAPIController::class, 'confirmDelivery']);

    Route::post('carriers', [App\Http\Controllers\API\CarrierAPIController::class, 'store']);

    // TEST ROUTES
    Route::post('testing-algorithm/drivers-by-carriers', [App\Http\Controllers\API\TestAPIController::class, 'searchNearDriverByCarrier']);
    
    Route::post('testing-algorithm/v1/nearest-carrier-and-drivers', [App\Http\Controllers\API\TestAPIController::class, 'getNearestCarrierAndDrivers']);
    
    Route::post('testing-algorithm/v1/onday-order-assignment', [App\Http\Controllers\API\TestAPIController::class, 'OndayOrderAssignment']);

    Route::post('testing-algorithm/v1/confirm', [App\Http\Controllers\API\TestAPIController::class, 'assign']);


    Route::resource('customer-addresses', App\Http\Controllers\API\CustomerAddressAPIController::class)
    ->except(['create', 'edit'])
    ->middleware("auth.customer:api-customers");

    // Customer Profiles
    Route::get('customer-profiles/list', [App\Http\Controllers\API\CustomerProfileAPIController::class, 'index']);
    Route::get('customer-profiles/{id}', [App\Http\Controllers\API\CustomerProfileAPIController::class, 'show']);
    Route::post('customer-profiles/create', [App\Http\Controllers\API\CustomerProfileAPIController::class, 'store']);
    Route::put('customer-profiles/{id}/update', [App\Http\Controllers\API\CustomerProfileAPIController::class, 'update']);
    Route::delete('customer-profiles/{id}/delete', [App\Http\Controllers\API\CustomerProfileAPIController::class, 'destroy']);

    Route::resource('drivers/transactions', App\Http\Controllers\API\TransactionAPIController::class)
     ->except(['create', 'edit'])->middleware("auth.driver:api-drivers");


    Route::get('zones-with-carriers', [App\Http\Controllers\API\ZoneAPIController::class, 'indexWithCarrier']);
    Route::resource('zones', App\Http\Controllers\API\ZoneAPIController::class)
        ->except(['create', 'edit']);

    Route::get('delivery-objects', [App\Http\Controllers\API\DeliveryObjectAPIController::class, 'index'])->name('api.delivery-objects.index');

    // Commercials
    Route::get('commercials/list', [App\Http\Controllers\API\CommercialAPIController::class, 'index']);
    Route::get('commercials/{id}', [App\Http\Controllers\API\CommercialAPIController::class, 'show']);
    Route::get('commercials/code/{code}', [App\Http\Controllers\API\CommercialAPIController::class, 'findByCode']);
    Route::post('commercials/create', [App\Http\Controllers\API\CommercialAPIController::class, 'store']);
    Route::put('commercials/{id}/update', [App\Http\Controllers\API\CommercialAPIController::class, 'update']);
    Route::delete('commercials/{id}/delete', [App\Http\Controllers\API\CommercialAPIController::class, 'destroy']);
});

//Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//    return $request->user();
//});
//
//
//Route::resource('services', App\Http\Controllers\API\ServiceAPIController::class)
//    ->except(['create', 'edit']);
//
//Route::resource('type-engins', App\Http\Controllers\API\TypeEnginAPIController::class)
//    ->except(['create', 'edit']);
//
//
//Route::resource('engins', App\Http\Controllers\API\EnginAPIController::class)
//    ->except(['create', 'edit']);
//
//
//Route::resource('drivers', App\Http\Controllers\API\DriverAPIController::class)
//    ->except(['create', 'edit']);
//
//Route::resource('engin-pictures', App\Http\Controllers\API\EnginPictureAPIController::class)
//    ->except(['create', 'edit']);
//
//Route::resource('products', App\Http\Controllers\API\ProductAPIController::class)
//    ->except(['create', 'edit']);
//
//Route::resource('product-types', App\Http\Controllers\API\ProductTypeAPIController::class)
//    ->except(['create', 'edit']);
//
//Route::resource('product-engin-relations', App\Http\Controllers\API\ProductEnginRelationAPIController::class)
//    ->except(['create', 'edit']);
//
//Route::resource('delivery-types', App\Http\Controllers\API\DeliveryTypeAPIController::class)
//    ->except(['create', 'edit']);
//
//Route::resource('orders', App\Http\Controllers\API\OrderAPIController::class)
//    ->except(['create', 'edit']);
//
//Route::resource('order-items', App\Http\Controllers\API\OrderItemAPIController::class)
//    ->except(['create', 'edit']);
//
//Route::resource('customers', App\Http\Controllers\API\CustomerAPIController::class)
//    ->except(['create', 'edit']);
//
//Route::resource('customer-devices', App\Http\Controllers\API\CustomerDeviceAPIController::class)
//    ->except(['create', 'edit']);
//
//Route::resource('invoices', App\Http\Controllers\API\InvoiceAPIController::class)
//    ->except(['create', 'edit']);
//
//Route::resource('payments', App\Http\Controllers\API\PaymentAPIController::class)
//    ->except(['create', 'edit']);
//
//Route::resource('order-invitations', App\Http\Controllers\API\OrderInvitationAPIController::class)
//    ->except(['create', 'edit']);
//
//
//Route::resource('route-points', App\Http\Controllers\API\RoutePointAPIController::class)
//    ->except(['create', 'edit']);
//
//Route::resource('route-point-histories', App\Http\Controllers\API\RoutePointHistoryAPIController::class)
//    ->except(['create', 'edit']);


//Route::resource('type-engin-models', App\Http\Controllers\API\TypeEnginModelAPIController::class)
//    ->except(['create', 'edit']);


//Route::resource('settings', App\Http\Controllers\API\SettingAPIController::class)
//    ->except(['create', 'edit']);


//Route::resource('driver-devices', App\Http\Controllers\API\DriverDeviceAPIController::class)
//    ->except(['create', 'edit']);

//Route::resource('driver-notifications', App\Http\Controllers\API\DriverNotificationsAPIController::class)
//    ->except(['create', 'edit']);

//Route::resource('customer-notifications', App\Http\Controllers\API\CustomerNotificationAPIController::class)
//    ->except(['create', 'edit']);


//Route::resource('slides', App\Http\Controllers\API\SlideAPIController::class)
  //  ->except(['create', 'edit']);


Route::resource('driver-otps', App\Http\Controllers\API\DriverOtpAPIController::class)
    ->except(['create', 'edit']);

Route::resource('driver-carriers', App\Http\Controllers\API\DriverCarrierAPIController::class)
    ->except(['create', 'edit']);

   
