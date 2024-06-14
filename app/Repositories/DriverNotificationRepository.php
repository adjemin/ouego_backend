<?php

namespace App\Repositories;

use App\Models\DriverNotifications;
use App\Repositories\BaseRepository;

class DriverNotificationRepository extends BaseRepository
{
    protected $fieldSearchable = [
        'driver_id',
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
        return DriverNotifications::class;
    }
}
