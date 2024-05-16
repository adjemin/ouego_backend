<?php

namespace App\Repositories;

use App\Models\OrderInvitation;
use App\Repositories\BaseRepository;

class OrderInvitationRepository extends BaseRepository
{
    protected $fieldSearchable = [
        'driver_id',
        'order_id',
        'is_waiting_acceptation',
        'acceptation_time',
        'rejection_time',
        'latitude',
        'longitude'
    ];

    public function getFieldsSearchable(): array
    {
        return $this->fieldSearchable;
    }

    public function model(): string
    {
        return OrderInvitation::class;
    }
}
