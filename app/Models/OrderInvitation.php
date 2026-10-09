<?php

namespace App\Models;

use App\Events\OrderAssigned;
use App\Events\OrderInvitationCancelled;
use App\Events\OrderInvitationUpdated;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderInvitation extends Model
{
    use SoftDeletes;

    const PENDING = "pending";
    const NOTIFIED = "notified";
    const ACCEPTED = "accepted";
    const REJECTED = "rejected";
    const TIMEOUT = "timeout";


    public $table = 'order_invitations';

    protected $appends = ['order'];

    public $fillable = [
        'driver_id',
        'order_id',
        'is_waiting_acceptation',
        'acceptation_time',
        'rejection_time',
        'latitude',
        'longitude',
        'trip_request_id',
        'index',
        'attempt_number',
        'status'
    ];

    protected $casts = [
        'driver_id' => 'integer',
        'order_id' => 'integer',
        'is_waiting_acceptation'=> 'boolean',
        'latitude' => 'double',
        'longitude' => 'double'
    ];

    public static array $rules = [

    ];

    public function getOrderAttribute(){
        return Order::where('id', $this->order_id)->first();
    }

    public function order(){
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    /**
     * Invite un chauffeur sur une commande et le notifie.
     * Ne fait rien si le chauffeur a déjà une invitation pour cette commande (en attente, refusée ou close) :
     * il n'est notifié qu'une seule fois.
     */
    public static function inviteDriver(int $orderId, Driver $driver): ?self
    {
        $exists = self::where([
            'driver_id' => $driver->id,
            'order_id' => $orderId,
        ])->exists();

        if ($exists) {
            return null;
        }

        $invitation = self::create([
            'driver_id' => $driver->id,
            'order_id' => $orderId,
            'is_waiting_acceptation' => true,
            'acceptation_time' => null,
            'rejection_time' => null,
            'latitude' => $driver->last_location_latitude,
            'longitude' => $driver->last_location_longitude,
        ]);

        event(new OrderAssigned($invitation));

        return $invitation;
    }

    /**
     * Retire de la liste des chauffeurs les invitations en attente de la requête, et les prévient en temps réel.
     * Les invitations sont traitées une à une : un update() en masse ne dirait pas qui prévenir.
     */
    public static function cancelWaiting(Builder $query, string $reason, array $attributes = []): void
    {
        $query->where('is_waiting_acceptation', true)->get()
            ->each(function (self $invitation) use ($reason, $attributes) {
                $invitation->update(['is_waiting_acceptation' => false] + $attributes);
                if ($invitation->driver_id) {
                    OrderInvitationCancelled::dispatch($invitation, $reason);
                }
            });
    }

    /**
     * Supprime les invitations en attente de la requête, en prévenant leurs chauffeurs en temps réel.
     */
    public static function deleteWaiting(Builder $query, string $reason): void
    {
        $query->where('is_waiting_acceptation', true)->get()
            ->each(function (self $invitation) use ($reason) {
                if ($invitation->driver_id) {
                    OrderInvitationCancelled::dispatch($invitation, $reason);
                }
                $invitation->delete();
            });
    }

    /**
     * Prévient les chauffeurs invités sur la commande que les informations de leur invitation ont changé.
     */
    public static function notifyWaitingDriversOfUpdate(int $orderId): void
    {
        self::where('order_id', $orderId)
            ->where('is_waiting_acceptation', true)
            ->whereNotNull('driver_id')
            ->get()
            ->each(fn (self $invitation) => OrderInvitationUpdated::dispatch($invitation));
    }

    /**
     * Payload temps réel d'une invitation (order.invitation.created / order.invitation.updated).
     */
    public function broadcastPayload(): array
    {
        $order = $this->order;
        $invoice = $order ? Invoice::where('order_id', $order->id)->first() : null;

        return [
            'invitation_id' => $this->id,
            'order_id' => $this->order_id,
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
            'order' => $order ? [
                'reference' => $order->reference,
                'service_slug' => $order->service_slug,
                'delivery_type_code' => $order->delivery_type_code,
                'driver_due' => $invoice?->driver_due,
                'currency_code' => $order->currency_code,
                'route_points' => RoutePoint::where('order_id', $order->id)
                    ->orderBy('visit_order')
                    ->get()
                    ->map(fn (RoutePoint $point) => $point->only(['type', 'address_name', 'latitude', 'longitude']))
                    ->all(),
            ] : null,
        ];
    }
}
