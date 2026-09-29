<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PaymentMethodsSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            [
                'name'       => 'Mobile money',
                'slug'       => 'mobile-money',
                'logo'       => 'https://ouego-dashboard.adjemin.com/documents/01K1XHBEA7YFEK9YVKBK3HNRC7.png',
                'created_at' => '2025-08-05 16:34:38',
                'updated_at' => '2025-08-05 16:34:38',
                'deleted_at' => null,
            ],
            [
                'name'       => 'Cash',
                'slug'       => 'cash',
                'logo'       => 'https://ouego-dashboard.adjemin.com/documents/01K1XHCP4H14EQYY4KCBJJ823B.png',
                'created_at' => '2025-08-05 16:34:51',
                'updated_at' => '2025-08-05 16:35:18',
                'deleted_at' => null,
            ],
        ];

        foreach ($methods as $method) {
            DB::table('payment_methods')->updateOrInsert(
                ['slug' => $method['slug']],
                $method
            );
        }
    }
}
