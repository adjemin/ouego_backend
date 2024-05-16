<?php

namespace App\Repositories;

use App\Models\TypeEngin;
use App\Repositories\BaseRepository;

class TypeEnginRepository extends BaseRepository
{
    protected $fieldSearchable = [
        'ability',
        'usages',
        'name',
        'slug',
        'models',
        'services'
    ];

    public function getFieldsSearchable(): array
    {
        return $this->fieldSearchable;
    }

    public function model(): string
    {
        return TypeEngin::class;
    }
}
