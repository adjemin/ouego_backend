<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    #$count = DB::table('pg_stat_activity')->count();
    # dd($count);
    return view('welcome');
});

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

Auth::routes();

Route::resource('services', App\Http\Controllers\ServiceController::class);
Route::resource('type-engins', App\Http\Controllers\TypeEnginController::class);

Route::resource('drivers', App\Http\Controllers\DriverController::class);
Route::resource('engin-pictures', App\Http\Controllers\EnginPictureController::class);
Route::resource('products', App\Http\Controllers\ProductController::class);
Route::resource('product-types', App\Http\Controllers\ProductTypeController::class);
Route::resource('product-engin-relations', App\Http\Controllers\ProductEnginRelationController::class);
Route::resource('delivery-types', App\Http\Controllers\DeliveryTypeController::class);
Route::resource('carriers', App\Http\Controllers\CarrierController::class);
Route::resource('orders', App\Http\Controllers\OrderController::class);
// Route::resource('order-pickups', App\Http\Controllers\OrderPickupController::class);
// Route::resource('order-deliveries', App\Http\Controllers\OrderDeliveryController::class);
Route::resource('order-items', App\Http\Controllers\OrderItemController::class);
Route::resource('customers', App\Http\Controllers\CustomerController::class);
Route::resource('customer-devices', App\Http\Controllers\CustomerDeviceController::class);
Route::resource('invoices', App\Http\Controllers\InvoiceController::class);
Route::resource('payments', App\Http\Controllers\PaymentController::class);
Route::resource('order-invitations', App\Http\Controllers\OrderInvitationController::class);
Route::resource('transactions', App\Http\Controllers\TransactionController::class);
Route::resource('settings', App\Http\Controllers\SettingController::class);