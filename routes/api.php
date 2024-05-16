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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});


Route::resource('services', App\Http\Controllers\API\ServiceAPIController::class)
    ->except(['create', 'edit']);

Route::resource('type-engins', App\Http\Controllers\API\TypeEnginAPIController::class)
    ->except(['create', 'edit']);


Route::resource('engins', App\Http\Controllers\API\EnginAPIController::class)
    ->except(['create', 'edit']);


Route::resource('drivers', App\Http\Controllers\API\DriverAPIController::class)
    ->except(['create', 'edit']);

Route::resource('engin-pictures', App\Http\Controllers\API\EnginPictureAPIController::class)
    ->except(['create', 'edit']);

Route::resource('products', App\Http\Controllers\API\ProductAPIController::class)
    ->except(['create', 'edit']);

Route::resource('product-types', App\Http\Controllers\API\ProductTypeAPIController::class)
    ->except(['create', 'edit']);

Route::resource('product-engin-relations', App\Http\Controllers\API\ProductEnginRelationAPIController::class)
    ->except(['create', 'edit']);

Route::resource('delivery-types', App\Http\Controllers\API\DeliveryTypeAPIController::class)
    ->except(['create', 'edit']);

Route::resource('carriers', App\Http\Controllers\API\CarrierAPIController::class)
    ->except(['create', 'edit']);

Route::resource('orders', App\Http\Controllers\API\OrderAPIController::class)
    ->except(['create', 'edit']);

Route::resource('order-pickups', App\Http\Controllers\API\OrderPickupAPIController::class)
    ->except(['create', 'edit']);

Route::resource('order-deliveries', App\Http\Controllers\API\OrderDeliveryAPIController::class)
    ->except(['create', 'edit']);

Route::resource('order-items', App\Http\Controllers\API\OrderItemAPIController::class)
    ->except(['create', 'edit']);

Route::resource('customers', App\Http\Controllers\API\CustomerAPIController::class)
    ->except(['create', 'edit']);

Route::resource('customer-devices', App\Http\Controllers\API\CustomerDeviceAPIController::class)
    ->except(['create', 'edit']);

Route::resource('invoices', App\Http\Controllers\API\InvoiceAPIController::class)
    ->except(['create', 'edit']);

Route::resource('payments', App\Http\Controllers\API\PaymentAPIController::class)
    ->except(['create', 'edit']);

Route::resource('order-invitations', App\Http\Controllers\API\OrderInvitationAPIController::class)
    ->except(['create', 'edit']);

Route::resource('transactions', App\Http\Controllers\API\TransactionAPIController::class)
    ->except(['create', 'edit']);