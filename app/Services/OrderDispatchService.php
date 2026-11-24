<?php

namespace App\Services;

use App\Models\Order;

/**
 * Lance (ou relance) la recherche de chauffeur d'une commande : utilisé par la confirmation client,
 * la confirmation d'un paiement en ligne et la relance après « aucun chauffeur trouvé ».
 */
class OrderDispatchService
{
    public function startDriverLookup(Order $order, string $actorTable, ?int $actorId): void
    {
        $order->update([
            'is_draft' => false,
            'order_date' => now(),
            'status' => Order::PERFORMER_LOOKUP,
        ]);

        $order->newOrderHistory(Order::PERFORMER_LOOKUP, $actorTable, $actorId);

        app(DriverAssignmentService::class)->sendInvitations($order, 10);
    }
}
