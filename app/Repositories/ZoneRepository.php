<?php

namespace App\Repositories;

use App\Models\Zone;
use App\Repositories\BaseRepository;

class ZoneRepository extends BaseRepository
{
    protected $fieldSearchable = [
        'name',
        'description',
        'zone_base_id',
        'geometry'
    ];

    public function getFieldsSearchable(): array
    {
        return $this->fieldSearchable;
    }

    public function model(): string
    {
        return Zone::class;
    }
}
