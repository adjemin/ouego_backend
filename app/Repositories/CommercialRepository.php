<?php

namespace App\Repositories;

use App\Models\Commercial;
use App\Repositories\BaseRepository;

class CommercialRepository extends BaseRepository
{
    protected $fieldSearchable = [
        'first_name',
        'last_name',
        'name',
        'phone',
        'email',
        'code',
        'is_active'
    ];

    public function getFieldsSearchable(): array
    {
        return $this->fieldSearchable;
    }

    public function model(): string
    {
        return Commercial::class;
    }
}
