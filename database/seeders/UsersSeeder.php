<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UsersSeeder extends Seeder
{
    public function run(): void
    {
        // Aucun mot de passe dans le code : SEED_ADMIN_PASSWORD s'il est défini, sinon un mot de passe aléatoire
        // (à réinitialiser ensuite). Les comptes existants gardent leur mot de passe actuel.
        $password = Hash::make(env('SEED_ADMIN_PASSWORD') ?: Str::random(32));

        $users = [
            [
                'name' => 'Ouego',
                'email' => 'admin.ouego@gmail.com',
                'email_verified_at' => null,
                'password' => $password,
                'remember_token' => null,
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
                'password' => $password,
                'remember_token' => null,
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
                'password' => $password,
                'remember_token' => null,
                'is_active' => true,
                'role' => 'super-admin',
                'deleted_at' => null,
                'created_at' => '2026-03-05 10:39:08',
                'updated_at' => '2026-03-05 10:39:08',
            ],
        ];

        foreach ($users as $user) {
            if (DB::table('users')->where('email', $user['email'])->exists()) {
                continue;
            }

            DB::table('users')->insert($user);
        }
    }
}
