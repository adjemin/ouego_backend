<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Index sur les colonnes de jointure et de filtrage les plus sollicitées
     * (recherche de chauffeurs, sérialisation des commandes, job d'attribution).
     */
    private array $indexes = [
        // Sous-requêtes de la recherche de chauffeurs (count par type, whereDoesntHave)
        'orders_driver_id_is_completed_index' => 'orders (driver_id, is_completed)',
        'orders_customer_id_created_at_index' => 'orders (customer_id, created_at)',
        // ProcessPendingOrderAssignments
        'orders_status_driver_id_index' => 'orders (status, driver_id)',
        'order_items_order_id_index' => 'order_items (order_id)',
        'route_points_order_id_index' => 'route_points (order_id)',
        'route_point_histories_route_point_id_index' => 'route_point_histories (route_point_id)',
        'invoices_order_id_index' => 'invoices (order_id)',
        'invoices_customer_id_coupon_index' => 'invoices (customer_id, coupon)',
        'payments_invoice_id_index' => 'payments (invoice_id)',
        'order_invitations_order_id_driver_id_index' => 'order_invitations (order_id, driver_id)',
        'order_invitations_driver_id_is_waiting_acceptation_index' => 'order_invitations (driver_id, is_waiting_acceptation)',
        'engins_driver_id_index' => 'engins (driver_id)',
        'customer_devices_customer_id_index' => 'customer_devices (customer_id)',
        'driver_devices_driver_id_index' => 'driver_devices (driver_id)',
        'settings_name_index' => 'settings (name)',
    ];

    public function up(): void
    {
        foreach ($this->indexes as $name => $definition) {
            DB::statement("CREATE INDEX IF NOT EXISTS {$name} ON {$definition}");
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->indexes) as $name) {
            DB::statement("DROP INDEX IF EXISTS {$name}");
        }
    }
};
