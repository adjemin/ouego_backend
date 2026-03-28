<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

use function Symfony\Component\String\b;

class UsersSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name' => 'Ouego',
                'email' => 'admin.ouego@gmail.com',
                'email_verified_at' => null,
                'password' => Hash::make('NcZ#96G4NWJx'),
                'remember_token' => 'GsYbMomBfnYrLEI5Q2r0yzZm5saIZGIPO3QAQFIeeb4rsOa4ELy3az8Pg3NZ',
                'is_active' => true,
                'role' => 'super-admin',
                'deleted_at' => null,
                'created_at' => '2025-08-06 07:34:49',
                'updated_at' => '2026-02-17 16:32:40',
            ],
            [
                'name' => 'Wilfried N\'Guessan',
                'email' => 'wilfried.nguessan@ouego.com',
                'email_verified_at' => null,
                'password' => Hash::make('NcZ#96G4NWJx'),
                'remember_token' => 'NtseIqq06UQSCEPzPqYg74Vxz8PUXKYMpGD69aVceA40O0O3TQbQIamiHvgI',
                'is_active' => true,
                'role' => 'super-admin',
                'deleted_at' => null,
                'created_at' => '2026-03-05 10:39:08',
                'updated_at' => '2026-03-05 10:39:08',
            ],
            [
                'name' => 'John Light',
                'email' => 'dev.johnlight@gmail.com',
                'email_verified_at' => null,
                'password' => Hash::make('Password123456@'),
                'remember_token' => 'NtseIqq06UQSCEPzPqYg74Vxz8PUXKYMpGD69aVceA40O0O3TQbQIamiHvgI',
                'is_active' => true,
                'role' => 'super-admin',
                'deleted_at' => null,
                'created_at' => '2026-03-05 10:39:08',
                'updated_at' => '2026-03-05 10:39:08',
            ],
        ];

        foreach ($users as $user) {
            DB::table('users')->updateOrInsert(
                ['email' => $user['email']],
                $user
            );
        }
    }
}
