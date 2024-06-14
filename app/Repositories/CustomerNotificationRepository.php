<?php

namespace App\Repositories;

use App\Models\CustomerNotification;
use App\Repositories\BaseRepository;

class CustomerNotificationRepository extends BaseRepository
{
    protected $fieldSearchable = [
        'customer_id',
        'title',
        'subtitle',
        'data_id',
        'type',
        'is_read',
        'is_received',
        'meta_data'
    ];

    public function getFieldsSearchable(): array
    {
        return $this->fieldSearchable;
    }

    public function model(): string
    {
        return CustomerNotification::class;
    }
}
