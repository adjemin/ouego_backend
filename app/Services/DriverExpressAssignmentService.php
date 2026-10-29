<?php

namespace App\Services;

use App\Models\DeliveryType;
use Illuminate\Database\Eloquent\Builder;

/**
 * Attribution des commandes EXPRESS : le chauffeur doit être libre tout de suite.
 */
class DriverExpressAssignmentService extends NearestDriverAssignmentService
{
    protected function applyDeliveryTypeRules(Builder $query): void
    {
        // Aucune course démarrée (tous types)
        $query->whereDoesntHave('orders', fn ($q) => $q->active()->started());

        // Aucune EXPRESS non terminée (même en attente)
        $query->whereDoesntHave('orders', fn ($q) => $q->active()->express()->where('is_completed', false));

        // Après l'heure limite des courses en journée : aucune course en journée en cours.
        // Le code d'origine passait « '>=', 3 » à whereDoesntHave(), qui ignore ces arguments :
        // la règle appliquée en production, conservée ici, exclut dès la première course en journée.
        if (now()->hour >= DeliveryType::enJourneeCutoffHour()) {
            $query->whereDoesntHave('orders', fn ($q) => $q->active()->day()->where('is_completed', false));
        }

        // Au plus trois courses de nuit en cours entre minuit et 7h
        if (now()->hour < 7) {
            $query->where($this->activeOrderCount('night'), '<', 3);
        }
    }
}
