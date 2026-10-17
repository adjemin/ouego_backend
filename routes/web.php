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
