<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CustomerProfile;
use App\Models\Driver;
use Illuminate\Database\Seeder;

/**
 * Comptes customer et driver de test (OTP fixe, voir config/otp.php).
 *
 * php artisan db:seed --class=TestAccountsSeeder
 */
class TestAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $customer = Customer::withTrashed()->updateOrCreate(
            ['phone' => '2250999000001'],
            [
                'first_name' => 'Customer',
                'last_name' => 'Test',
                'name' => 'Customer Test',
                'dialing_code' => '225',
                'phone_number' => '0999000001',
                'email' => 'customer.test@ouego.app',
                'is_active' => true,
                'is_phone_verified' => true,
                'profile_id' => CustomerProfile::where('name', 'Particulier')->value('id'),
            ]
        );
        $customer->restore();

        $driver = Driver::withTrashed()->updateOrCreate(
            ['phone' => '2250999000002'],
            [
                'first_name' => 'Driver',
                'last_name' => 'Test',
                'name' => 'Driver Test',
                'dialing_code' => '225',
                'phone_number' => '0999000002',
                'is_active' => true,
                'rate' => 5,
            ]
        );
        $driver->restore();
    }
}
