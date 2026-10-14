<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Driver;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class AuthGuardMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/_test/customer-error', fn () => throw new RuntimeException('panne'))
            ->middleware('auth.customer:api-customers');
        Route::get('/_test/driver-error', fn () => throw new RuntimeException('panne'))
            ->middleware('auth.driver:api-drivers');
    }

    /** @test */
    public function a_controller_error_on_a_customer_route_is_a_server_error_not_a_401()
    {
        $customer = Customer::create(['name' => 'Client Test', 'phone' => '2250700000000', 'is_active' => true]);

        $this->actingAs($customer, 'api-customers')
            ->getJson('/_test/customer-error')
            ->assertStatus(500);
    }

    /** @test */
    public function a_controller_error_on_a_driver_route_is_a_server_error_not_a_401()
    {
        $this->actingAs(Driver::factory()->create(), 'api-drivers')
            ->getJson('/_test/driver-error')
            ->assertStatus(500);
    }

    /** @test */
    public function a_missing_token_is_still_a_401()
    {
        $this->getJson('/_test/customer-error')->assertStatus(401);
        $this->getJson('/_test/driver-error')->assertStatus(401);
    }

    /** @test */
    public function a_blocked_customer_is_still_rejected()
    {
        $customer = Customer::create(['name' => 'Client Bloqué', 'phone' => '2250700000001', 'is_active' => true]);
        $customer->forceFill(['is_blocked' => true])->save();

        $this->actingAs($customer, 'api-customers')
            ->getJson('/_test/customer-error')
            ->assertStatus(403);
    }
}
