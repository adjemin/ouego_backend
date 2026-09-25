<?php

use App\Models\Order;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Suivi d'une commande : réservé au client qui l'a passée
Broadcast::channel('orders.{orderId}', function ($customer, $orderId) {
    return Order::whereKey($orderId)->where('customer_id', $customer->id)->exists();
}, ['guards' => ['api-customers']]);

// Liste des commandes du client (customers/orders/list) : nouvelles commandes et changements de statut
Broadcast::channel('customers.{customerId}', function ($customer, $customerId) {
    return (int) $customer->id === (int) $customerId;
}, ['guards' => ['api-customers']]);
