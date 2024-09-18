<?php

namespace App\Repositories;

use App\Models\CustomerOTP;
use App\Repositories\BaseRepository;

class CustomerOTPRepository extends BaseRepository
{
    protected $fieldSearchable = [
        'otp',
        'otp_expires_at',
        'phone',
        'is_test_mode'
    ];

    public function getFieldsSearchable(): array
    {
        return $this->fieldSearchable;
    }

    public function model(): string
    {
        return CustomerOTP::class;
    }
}
