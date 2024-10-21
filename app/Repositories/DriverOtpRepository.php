<?php

namespace App\Repositories;

use App\Models\DriverOtp;
use App\Repositories\BaseRepository;

class DriverOtpRepository extends BaseRepository
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
        return DriverOtp::class;
    }
}
