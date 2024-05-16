<?php

namespace App\Repositories;

use App\Models\EnginPicture;
use App\Repositories\BaseRepository;

class EnginPictureRepository extends BaseRepository
{
    protected $fieldSearchable = [
        'engin_id',
        'url'
    ];

    public function getFieldsSearchable(): array
    {
        return $this->fieldSearchable;
    }

    public function model(): string
    {
        return EnginPicture::class;
    }
}
