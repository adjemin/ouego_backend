<?php

namespace App\Services;

/**
 * Attribution des commandes en semaine.
 */
class DriverEnSemaineAssignmentService extends NearestDriverAssignmentService
{
    /**
     * Exception du dernier jour (règle 4) : du lundi au jeudi, une location qui se termine aujourd'hui
     * ne bloque pas le chauffeur. À partir du vendredi, toute location en cours bloque.
     */
    protected function blockingRentalEndOperator(): string
    {
        return now()->dayOfWeekIso >= 5 ? '>=' : '>';
    }
}
