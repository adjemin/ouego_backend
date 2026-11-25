<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Lance (ou relance) la recherche de chauffeur d'une commande : utilisé par la confirmation client,
 * la confirmation d'un paiement en ligne et la relance après « aucun chauffeur trouvé ».
 */
class OrderDispatchService
{
    /** Seuls états d'où une recherche peut partir. */
    public const LAUNCHABLE = [Order::INITIATED, Order::PERFORMER_NOT_FOUND];

    /**
     * Passe la commande en PERFORMER_LOOKUP, sous verrou, seulement depuis un état LAUNCHABLE d'une commande
     * ouverte : une annulation ou une acceptation concurrente n'est jamais écrasée.
     * Renvoie false si rien n'a été lancé ; $order est rechargé dans tous les cas.
     */
    public function startDriverLookup(Order $order, string $actorTable, ?int $actorId): bool
    {
        $started = DB::transaction(function () use ($order, $actorTable, $actorId) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->first();
            if (! $locked || ! in_array($locked->status, self::LAUNCHABLE, true) || $locked->is_completed) {
                return false;
            }

            $locked->update([
                'is_draft' => false,
                'order_date' => now(),
                'status' => Order::PERFORMER_LOOKUP,
            ]);
            $locked->newOrderHistory(Order::PERFORMER_LOOKUP, $actorTable, $actorId);

            return true;
        });

        $order->refresh();

        if ($started) {
            app(DriverAssignmentService::class)->sendInvitations($order, 10);
        }

        return $started;
    }
}
