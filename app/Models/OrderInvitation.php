<?php

namespace App\Models;

use App\Events\OrderAssigned;
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



}
