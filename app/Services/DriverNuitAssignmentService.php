<?php

namespace App\Services;

/**
 * Attribution des commandes de nuit : règles communes uniquement.
 * La fenêtre de recherche (20h - 07h) est gérée par DriverAssignmentService.
 */
class DriverNuitAssignmentService extends NearestDriverAssignmentService
{
}
