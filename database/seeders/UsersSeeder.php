<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UsersSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name' => 'Ouego',
                'email' => 'admin.ouego@gmail.com',
                'email_verified_at' => null,
                'password' => '$2y$12$4p/kO3nIwaseASm8SwQwxObE4JJ7we3.QhGapeOE0iYU/Q/mc0bmq',
                'remember_token' => 'GsYbMomBfnYrLEI5Q2r0yzZm5saIZGIPO3QAQFIeeb4rsOa4ELy3az8Pg3NZ',
                'is_active' => true,
                'role' => 'super-admin',
                'deleted_at' => null,
                'created_at' => '2025-08-06 07:34:49',
                'updated_at' => '2026-02-17 16:32:40',
            ],
            [
                'name' => 'Adams',
                'email' => 'adams@ouego.com',
                'email_verified_at' => null,
                'password' => '$2y$12$KUzkeOEcZPzvfK8Sn9M6/uQihy6f1hw617nUhnx3acBKsUUbgSZES',
                'remember_token' => null,
                'is_active' => true,
                'role' => 'super-admin',
                'deleted_at' => null,
                'created_at' => '2026-02-17 17:49:15',
                'updated_at' => '2026-02-17 17:49:15',
            ],
            [
                'name' => 'Wilfried N\'Guessan',
                'email' => 'wilfried.nguessan@ouego.com',
                'email_verified_at' => null,
                'password' => '$2y$12$NmjsPbBknehGMeELE7SCuuS8iq1iJNRcVI05Le9f5zT0DfQ63lj.y',
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
