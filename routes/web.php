<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Le back-office web a été retiré : le backend n'expose plus que l'API (routes/api.php).
|
*/

Route::get('/', function () {
    return view('welcome');
});

// Retour du client après la page de paiement de l'opérateur (successUrl / errorUrl) : redirige vers l'app
Route::get('payments/return/{reference}/{result}', [App\Http\Controllers\PaymentReturnController::class, 'show'])->name('payments.return');
