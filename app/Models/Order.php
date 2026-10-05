<?php

namespace App\Models;

use App\Events\OrderCreated;
use App\Events\OrderStatusUpdated;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    public $table = 'orders';

    //STATUS
    const INITIATED = "initiated";
    const NEW = "new";
    const ESTIMATING = "estimating";
    //estimating_failed
    const ESTIMATING_FAILED = "estimating_failed";
    //ready_for_approval
    const READY_FOR_APPROVAL = "ready_for_approval";
    //failed
    const FAILED = "failed";
    //accepted A claim must be confirmed within n 10 minutes, otherwise it’ll get t «failed» status
    const ACCEPTED = "accepted";
    //performer_lookup
    const PERFORMER_LOOKUP = "performer_lookup";
    //performer_draft
    const PERFORMER_DRAFT ="performer_draft";
    //performer_not_found
    const PERFORMER_NOT_FOUND = "performer_not_found";
    //cancelled_by_taxi
    const CANCELLED_BY_TAXI = "cancelled_by_taxi";
    //performer_found
    const PERFORMER_FOUND = "performer_found";
    //pickup_arrived
    const PICKUP_ARRIVED = "pickup_arrived";
    //ready_for_pickup_confirmation
    const READY_FOR_PICKUP_CONFIRMATION = "ready_for_pickup_confirmation";
    //pickuped //Now you can cancel a claim only contacting support, editing must be done with /v2/claims/apply-changes/request request
    const PICKUPED = "pickuped";
    //delivery_arrived //Courier tries to reach out a client  within 10 minutes at least. If it’s impossible, a parcel needs to be returned
    const DELIVERY_ARRIVED = "delivery_arrived";
    //pay_waiting //This status appears only while paying upon receipt
    const PAY_WAITING = "pay_waiting";
    //ready_for_delivery_confirmation
    const READY_FOR_DELIVERY_CONFIRMATION  = "ready_for_delivery_confirmation";
    //returning
    const RETURNING = "returning";
    //return_arrived
    const RETURN_ARRIVED = "return_arrived";
    //ready_for_return_confirmation
    const READY_FOR_RETURN_CONFIRMATION  = "ready_for_return_confirmation";
    //returned_finish Both delivered and delivered_finish statuses can be considered as final
    const RETURNED_FINISH = "returned_finish";
    //delivered  //Both delivered and delivered_finish statuses can be considered as final
    const DELIVERED = "delivered";
    //delivered_finish //Both delivered and delivered_finish statuses can be considered as final
    const DELIVERED_FINISH = "delivered_finish";
    //cancelled_with_items_on_hands If you’ll specify optional_return as true, packages won’t be returned
    const CANCELLED_WITH_ITEMS_ON_HANDS = "cancelled_with_items_on_hands";
    //cancelled Final status for free cancellation
    const CANCELLED = "cancelled";
    //cancelled_with_payment //Final status for paid cancellation
    const CANCELLED_WITH_PAYMENT = "cancelled_with_payment";


   CONST PAYMENT_MODE_CASH = "cash";
   CONST PAYMENT_MODE_ONLINE = "online";

    // Fenêtre de recherche des chauffeurs pour les commandes de nuit : de 20h à 07h
    const NIGHT_LOOKUP_START_HOUR = 20;
    const NIGHT_LOOKUP_END_HOUR = 7;
    // Délai standard (en minutes) avant de déclarer « chauffeur non trouvé »
    const PERFORMER_LOOKUP_TIMEOUT = 5;

    protected $appends = ['customer','driver', 'service','items', 'invoice', 'route_points'];

    public $fillable = [
        'reference',
        'customer_id',
        'driver_id',
        'service_slug',
        'status',
        'comment',
        'order_object',
        'order_date',
        'is_started',
        'is_running',
        'is_waiting',
        'is_completed',
        'is_successful',
        'completion_time',
        'start_time',
        'acceptation_time',
        'expected_arrival_at',
        'rating_id',
        'rating',
        'rating_note',
        'order_price',
        'currency_code',
        'payment_method_code',
        'delivery_type_code',
        'delivery_price',
        'manutention_pricing',
        'is_location',
        'is_product',
        'is_ride',
        'is_draft',
        'public_token',
        'public_token_expires_at'
    ];

    protected $casts = [
        'reference' => 'string',
        'customer_id' => 'integer',
        'driver_id' => 'integer',
        'service_slug' => 'string',
        'status' => 'string',
        'comment' => 'string',
        'is_started' => 'boolean',
        'is_running' => 'boolean',
        'is_waiting' => 'boolean',
        'is_completed' => 'boolean',
        'is_successful' => 'boolean',
        'rating_id' => 'integer',
        'rating' => 'integer',
        'rating_note' => 'string',
        'order_price' => 'double',
        'delivery_price'=> 'double',
        'manutention_pricing' => 'double',
        'currency_code' => 'string',
        'payment_method_code' => 'string',
        'delivery_type_code' => 'string',
        'is_location' => 'boolean',
        'is_product' => 'boolean',
        'is_ride' => 'boolean',
        'is_draft' => 'boolean',
        'public_token' => 'string',
        'public_token_expires_at' => 'datetime'
    ];

    public static array $rules = [

    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($order) {
            $order->public_token = Str::random(32);
        });

        static::created(function (Order $order) {
            if ($order->customer_id) {
                OrderCreated::dispatch($order);
            }
        });

        // Branché sur le modèle plutôt que sur newOrderHistory() : certains changements
        // de statut (TripRequestAPIController, ReassignNightOrders) ne créent pas d'historique.
        static::updated(function (Order $order) {
            if ($order->wasChanged(['status', 'driver_id'])) {
                OrderStatusUpdated::dispatch($order, $order->getOriginal('status'));
            }
        });
    }

    // Generate unique reference
    public static function generateReference(){

        $tries = 0;
        //$length = 8;
        do{
            /*$chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $ret = '';
            for($i = 0; $i < $length; ++$i) {
                $random = str_shuffle($chars);
                $ret .= $random[0];
            }*/
            $day_unique_reference = strtoupper(explode('-',Str::uuid())[0]);
            $ref = date("Y-m-d")."-".$day_unique_reference;
            $exists = Order::where(['reference'=> $ref])->first();
            $tries++;
        } while($exists && $tries < 3);

        return $ref;
    }

    public function getItemsAttribute(){
        return OrderItem::where('order_id', $this->id)->get();
    }

    public function invoice(){
        return $this->hasOne(Invoice::class, 'order_id', 'id');
    }

    public function getInvoiceAttribute(){
        return Invoice::where('order_id', $this->id)->first();
    }

    public function getRoutePointsAttribute()
    {
        return RoutePoint::where(['order_id' => $this->id])->orderBy('visit_order', 'ASC')->get();
    }

    public function customer(){
        return $this->belongsTo(Customer::class, 'customer_id', 'id');
    }

    public function getCustomerAttribute()
    {

        return Customer::where(['id' => $this->customer_id])->first();
    }

    public function getDriverAttribute()
    {

        return Driver::where(['id' => $this->driver_id])->first();
    }

    public function getSource()
    {
        return RoutePoint::where([
            'type' => 'source',
            'order_id' => $this->id
        ])->first();
    }

    public function getDestinations()
    {
        return RoutePoint::where([
            'type' => 'destination',
            'order_id' => $this->id
        ])->get();
    }


    public function getServiceAttribute()
    {
        return Service::where('slug', $this->service_slug)->first();
    }

    //chargeDriver()
    public function chargeDriver(){

        if($this->driver_id != null){

            $driver = Driver::where(['id' => $this->driver_id])->first();

            if($driver != null){
                if($this->getInvoiceAttribute() != null){
                    if($this->payment_method_code == Order::PAYMENT_MODE_CASH){
                        $amount = $this->getInvoiceAttribute()->service_due;
                        $driver->debitBalance($amount, $this->id);
                    }else{
                        $amount = $this->getInvoiceAttribute()->driver_due;
                        $driver->creditBalance($amount, $this->id);
                    }
                }

            }

        }

    }

    // Ajout de la relation orderInvitations
    public function orderInvitations(): HasMany
    {
        return $this->hasMany(OrderInvitation::class, 'order_id');
    }

    

    public function orderHistories(): HasMany
    {
        return $this->hasMany(OrderHistory::class, 'order_id')->orderBy('created_at', 'DESC');
    }
    

    public function newOrderHistory($status, $creator=null, $creator_id = null){
        OrderHistory::create([
            'order_id' => $this->id,
            'status' => $status,
            'creator' => $creator,
            'created_id' => $creator_id
        ]);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class, 'order_id', 'id');
    }

    public static function isNightLookupWindow(?Carbon $at = null): bool
    {
        $hour = ($at ?? now())->hour;

        return $hour >= self::NIGHT_LOOKUP_START_HOUR || $hour < self::NIGHT_LOOKUP_END_HOUR;
    }

    public function isNightDelivery(): bool
    {
        return !$this->is_location && $this->delivery_type_code == DeliveryType::TYPE_DE_NUIT;
    }

    /**
     * Moment de la confirmation par le client (order_date), ou de la création à défaut.
     */
    public function confirmedAt(): Carbon
    {
        return $this->order_date ? Carbon::parse($this->order_date) : $this->created_at->copy();
    }

    /**
     * Moment à partir duquel on cherche des chauffeurs.
     * Une commande de nuit confirmée en journée attend l'ouverture de la fenêtre de nuit (20h).
     */
    public function performerLookupStartsAt(): Carbon
    {
        $start = $this->confirmedAt();

        if ($this->isNightDelivery() && !self::isNightLookupWindow($start)) {
            return $start->setTime(self::NIGHT_LOOKUP_START_HOUR, 0);
        }

        return $start;
    }

    /**
     * Moment après lequel une commande toujours sans chauffeur passe en « chauffeur non trouvé ».
     *  - location : début du jour de location (au moins le délai standard après la confirmation)
     *  - nuit : 07h, fin de la fenêtre de nuit
     *  - autres : délai standard après la confirmation
     */
    public function performerLookupDeadline(): Carbon
    {
        $start = $this->performerLookupStartsAt();
        $standardDeadline = $start->copy()->addMinutes(self::PERFORMER_LOOKUP_TIMEOUT);

        if ($this->is_location) {
            $item = $this->orderItems->firstWhere('service_slug', Service::LOCATION);

            if ($item && $item->location_start_date) {
                return Carbon::parse($item->location_start_date)->startOfDay()->max($standardDeadline);
            }

            return $standardDeadline;
        }

        if ($this->isNightDelivery()) {
            $deadline = $start->copy()->setTime(self::NIGHT_LOOKUP_END_HOUR, 0);

            return $start->hour >= self::NIGHT_LOOKUP_START_HOUR ? $deadline->addDay() : $deadline;
        }

        return $standardDeadline;
    }


    // Order scops for driver
   
    public function scopeActive($q)
    {
        return $q->where('is_draft', false)
                ->where('is_completed', false);
    }

    public function scopeStarted($q)
    {
        return $q->where(function ($qq) {
            $qq->where('is_started', true)
            ->orWhere('is_running', true);
        });
    }

    public function scopeNotStarted($q)
    {
        // "reçue mais non démarrée"
        return $q->where('is_started', false)
                ->where('is_running', false);
        // si chez vous "non démarrée" = is_waiting=true, tu peux ajouter:
        // ->where('is_waiting', true);
    }

    public function scopeExpress($q)
    {
        return $q->where('delivery_type_code', DeliveryType::TYPE_EXPRESS);
    }

    public function scopeDay($q)
    {
        return $q->where('delivery_type_code', DeliveryType::TYPE_EN_JOURNEE);
    }

    public function scopeWeek($q)
    {
        return $q->where('delivery_type_code', DeliveryType::TYPE_DE_SEMAINE);
    }

    public function scopeNight($q)
    {
        return $q->where('delivery_type_code', DeliveryType::TYPE_DE_NUIT);
    }

}
