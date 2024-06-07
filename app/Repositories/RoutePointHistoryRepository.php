<?php

namespace App\Repositories;

use App\Models\RoutePointHistory;
use App\Repositories\BaseRepository;

class RoutePointHistoryRepository extends BaseRepository
{
    protected $fieldSearchable = [
        'route_point_id',
        'latitude',
        'longitude',
        'status'
    ];

    public function getFieldsSearchable(): array
    {
        return $this->fieldSearchable;
    }

    public function model(): string
    {
        return RoutePointHistory::class;
    }
}
