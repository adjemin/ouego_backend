<?php

namespace Tests\Feature\Auth;

use App\Models\CustomerOTP;
use App\Models\Driver;
use App\Models\DriverOtp;
use App\Services\OrangeSMSService;
use Tests\TestCase;

/**
 * L'OTP n'est transmis que par SMS : aucune réponse API ne doit le contenir,
 * sinon le seul numéro de téléphone suffit pour se connecter au compte.
 */
class OtpLeakTest extends TestCase
{
    private const PHONE = '2250700000001';

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(OrangeSMSService::class)->shouldReceive('sendSMS');
    }

    /** @test */
    public function customer_send_otp_does_not_return_the_code()
    {
        $response = $this->postJson('api/v1/customers/send-otp', ['phone' => self::PHONE])
            ->assertOk();

        $this->assertArrayNotHasKey('otp', $response->json('data'));
        $this->assertStringNotContainsString(
            CustomerOTP::where('phone', self::PHONE)->value('otp'),
            $response->getContent()
        );
    }

    /** @test */
    public function driver_send_otp_does_not_return_the_code()
    {
        $response = $this->postJson('api/v1/drivers/send-otp', ['phone' => self::PHONE])
            ->assertOk();

        $this->assertArrayNotHasKey('otp', $response->json('data'));
        $this->assertStringNotContainsString(
            DriverOtp::where('phone', self::PHONE)->value('otp'),
            $response->getContent()
        );
    }

    /** @test */
    public function the_code_received_by_sms_still_logs_the_driver_in()
    {
        Driver::factory()->create(['phone' => self::PHONE]);

        $this->postJson('api/v1/drivers/send-otp', ['phone' => self::PHONE])->assertOk();

        $this->postJson('api/v1/drivers/verify-otp', [
            'phone' => self::PHONE,
            'otp' => DriverOtp::where('phone', self::PHONE)->value('otp'),
        ])->assertOk()->assertJsonPath('data.user.phone', self::PHONE);
    }

    /** @test */
    public function driver_otps_are_not_exposed_by_a_public_crud()
    {
        DriverOtp::create([
            'phone' => self::PHONE,
            'otp' => '424242',
            'otp_expires_at' => now()->addMinutes(5),
        ]);

        $this->getJson('api/driver-otps')->assertNotFound();
        $this->postJson('api/driver-otps', [
            'phone' => self::PHONE,
            'otp' => '000000',
            'otp_expires_at' => now()->addHour(),
        ])->assertNotFound();
    }
}
