<?php

namespace Tests\Feature\Auth;

use App\Models\Customer;
use App\Models\CustomerDevice;
use App\Models\CustomerProfile;
use App\Models\Driver;
use App\Models\DriverDevice;
use App\Models\Order;
use App\Models\OrderInvitation;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

/**
 * Suppression de son propre compte par le client (DELETE customers/account)
 * et par le chauffeur (DELETE drivers/account).
 */
class AccountDeletionTest extends TestCase
{
    private const PHONE = '2250700000001';

    private function createCustomer(): Customer
    {
        return Customer::create(['name' => 'Client Test', 'phone' => self::PHONE, 'is_active' => true]);
    }

    private function createDriver(array $attributes = []): Driver
    {
        return Driver::factory()->create($attributes + ['phone' => self::PHONE, 'current_balance' => 0]);
    }

    private function createOrder(array $attributes): Order
    {
        return Order::create($attributes + [
            'reference' => Order::generateReference(),
            'status' => Order::INITIATED,
            'currency_code' => 'XOF',
            'is_completed' => false,
        ]);
    }

    // ---------------------------------------------------------------
    // Client
    // ---------------------------------------------------------------

    /** @test */
    public function a_customer_can_delete_their_account()
    {
        $customer = $this->createCustomer();
        CustomerDevice::create(['customer_id' => $customer->id, 'firebase_id' => 'fcm-token']);
        $token = JWTAuth::fromUser($customer);

        $this->withToken($token)->deleteJson('api/v1/customers/account')->assertOk();

        $this->assertSoftDeleted($customer);
        $this->assertDatabaseMissing('customer_devices', ['customer_id' => $customer->id]);
    }

    /** @test */
    public function the_customer_token_no_longer_works_after_deletion()
    {
        $token = JWTAuth::fromUser($this->createCustomer());

        $this->withToken($token)->deleteJson('api/v1/customers/account')->assertOk();

        $this->withToken($token)->getJson('api/v1/customers/get_profil')->assertUnauthorized();
    }

    /** @test */
    public function a_customer_with_an_order_in_progress_cannot_delete_their_account()
    {
        $customer = $this->createCustomer();
        $this->createOrder(['customer_id' => $customer->id, 'status' => Order::PERFORMER_FOUND]);

        $this->withToken(JWTAuth::fromUser($customer))
            ->deleteJson('api/v1/customers/account')
            ->assertStatus(400);

        $this->assertNotSoftDeleted($customer);
    }

    /** @test */
    public function finished_or_cancelled_orders_do_not_block_the_customer_deletion()
    {
        $customer = $this->createCustomer();
        $this->createOrder(['customer_id' => $customer->id, 'status' => Order::DELIVERED, 'is_completed' => true]);
        $this->createOrder(['customer_id' => $customer->id, 'status' => Order::CANCELLED]);

        $this->withToken(JWTAuth::fromUser($customer))
            ->deleteJson('api/v1/customers/account')
            ->assertOk();

        $this->assertSoftDeleted($customer);
    }

    /** @test */
    public function a_deleted_customer_can_register_again_with_the_same_phone()
    {
        $this->withToken(JWTAuth::fromUser($this->createCustomer()))
            ->deleteJson('api/v1/customers/account')
            ->assertOk();

        $this->postJson('api/v1/customers/register', [
            'first_name' => 'client',
            'last_name' => 'test',
            'dialing_code' => '225',
            'phone_number' => '0700000001',
            'profile_id' => CustomerProfile::create(['name' => 'Particulier'])->id,
        ])->assertOk()->assertJsonPath('data.user.phone', self::PHONE);
    }

    /** @test */
    public function deleting_a_customer_account_requires_authentication()
    {
        $this->deleteJson('api/v1/customers/account')->assertUnauthorized();
    }

    // ---------------------------------------------------------------
    // Chauffeur
    // ---------------------------------------------------------------

    /** @test */
    public function a_driver_can_delete_their_account()
    {
        $driver = $this->createDriver();
        DriverDevice::create(['driver_id' => $driver->id, 'firebase_id' => 'fcm-token']);

        $this->withToken(JWTAuth::fromUser($driver))->deleteJson('api/v1/drivers/account')->assertOk();

        $this->assertSoftDeleted($driver);
        $this->assertFalse(Driver::withTrashed()->find($driver->id)->is_available);
        $this->assertDatabaseMissing('driver_devices', ['driver_id' => $driver->id]);
    }

    /** @test */
    public function the_driver_token_no_longer_works_after_deletion()
    {
        $token = JWTAuth::fromUser($this->createDriver());

        $this->withToken($token)->deleteJson('api/v1/drivers/account')->assertOk();

        $this->withToken($token)->getJson('api/v1/drivers/get_profil')->assertUnauthorized();
    }

    /** @test */
    public function a_driver_with_an_order_in_progress_cannot_delete_their_account()
    {
        $driver = $this->createDriver();
        $customer = $this->createCustomer();
        $this->createOrder([
            'customer_id' => $customer->id,
            'driver_id' => $driver->id,
            'status' => Order::PICKUPED,
        ]);

        $this->withToken(JWTAuth::fromUser($driver))
            ->deleteJson('api/v1/drivers/account')
            ->assertStatus(400);

        $this->assertNotSoftDeleted($driver);
    }

    /** @test */
    public function a_driver_with_a_non_zero_balance_cannot_delete_their_account()
    {
        $driver = $this->createDriver(['current_balance' => 1500]);

        $this->withToken(JWTAuth::fromUser($driver))
            ->deleteJson('api/v1/drivers/account')
            ->assertStatus(400);

        $this->assertNotSoftDeleted($driver);
    }

    /** @test */
    public function deleting_a_driver_account_releases_their_pending_invitations()
    {
        $driver = $this->createDriver();
        $order = $this->createOrder(['customer_id' => $this->createCustomer()->id, 'status' => Order::PERFORMER_LOOKUP]);
        $invitation = OrderInvitation::create([
            'driver_id' => $driver->id,
            'order_id' => $order->id,
            'is_waiting_acceptation' => true,
        ]);

        $this->withToken(JWTAuth::fromUser($driver))->deleteJson('api/v1/drivers/account')->assertOk();

        $this->assertFalse($invitation->fresh()->is_waiting_acceptation);
    }

    /** @test */
    public function deleting_a_driver_account_requires_authentication()
    {
        $this->deleteJson('api/v1/drivers/account')->assertUnauthorized();
    }
}
