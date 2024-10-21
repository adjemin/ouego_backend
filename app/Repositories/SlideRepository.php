<?php

namespace App\Repositories;

use App\Models\Slide;
use App\Repositories\BaseRepository;

class SlideRepository extends BaseRepository
{
    protected $fieldSearchable = [
        'title',
        'description',
        'image_url',
        'is_active',
        'color'
    ];

    public function getFieldsSearchable(): array
    {
        return $this->fieldSearchable;
    }

    public function model(): string
    {
        return Slide::class;
    }
}
