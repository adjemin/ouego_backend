<?php

namespace Tests\Feature\Auth;

use App\Models\Customer;
use App\Models\CustomerOTP;
use App\Models\Driver;
use App\Models\DriverOtp;
use App\Services\OrangeSMSService;
use Tests\TestCase;

/**
 * Parcours de connexion par OTP des deux applications : envoi du code, vérification,
 * puis utilisation, actualisation et révocation du jeton JWT obtenu.
 */
class OtpAuthenticationFlowTest extends TestCase
{
    private const PHONE = '2250700000001';

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(OrangeSMSService::class)->shouldReceive('sendSMS');
    }

    public static function apps(): array
    {
        return [
            'client' => ['customers', CustomerOTP::class],
            'chauffeur' => ['drivers', DriverOtp::class],
        ];
    }

    /**
     * @test
     * @dataProvider apps
     */
    public function the_otp_received_by_sms_gives_a_working_token(string $app, string $otpModel)
    {
        $this->createAccount($app);

        $token = $this->verifyOtp($app, $this->sendOtp($app, $otpModel))
            ->assertOk()
            ->assertJsonPath('data.user.phone', self::PHONE)
            ->assertJsonPath('data.token_type', 'bearer')
            ->json('data.token');

        $this->withToken($token)
            ->getJson("api/v1/{$app}/get_profil")
            ->assertOk();
    }

    /**
     * @test
     * @dataProvider apps
     */
    public function a_wrong_otp_is_refused(string $app, string $otpModel)
    {
        $this->createAccount($app);
        $otp = $this->sendOtp($app, $otpModel);

        $this->verifyOtp($app, $otp === '000000' ? '111111' : '000000')
            ->assertStatus(400)
            ->assertJsonMissingPath('data.token');
    }

    /**
     * @test
     * @dataProvider apps
     */
    public function an_expired_otp_is_refused(string $app, string $otpModel)
    {
        $this->createAccount($app);
        $otp = $this->sendOtp($app, $otpModel);

        $this->travel(1)->days();

        $this->verifyOtp($app, $otp)->assertStatus(400);
    }

    /**
     * @test
     * @dataProvider apps
     */
    public function an_otp_can_only_be_used_once(string $app, string $otpModel)
    {
        $this->createAccount($app);
        $otp = $this->sendOtp($app, $otpModel);

        $this->verifyOtp($app, $otp)->assertOk();
        $this->verifyOtp($app, $otp)->assertStatus(422);
    }

    /**
     * @test
     * @dataProvider apps
     */
    public function a_blocked_account_gets_no_token(string $app, string $otpModel)
    {
        $this->createAccount($app)->forceFill(['is_blocked' => true])->save();
        // Code créé directement : l'envoi par SMS est lui-même refusé aux chauffeurs bloqués
        $otpModel::create(['phone' => self::PHONE, 'otp' => '424242', 'otp_expires_at' => now()->addMinutes(5)]);

        $this->verifyOtp($app, '424242')
            ->assertStatus(403)
            ->assertJsonMissingPath('data.token');
    }

    /**
     * @test
     * @dataProvider apps
     */
    public function a_token_can_be_refreshed_and_revoked(string $app, string $otpModel)
    {
        $this->createAccount($app);
        $token = $this->verifyOtp($app, $this->sendOtp($app, $otpModel))->json('data.token');

        $refreshed = $this->withToken($token)
            ->postJson("api/v1/{$app}/refresh")
            ->assertOk()
            ->json('data.token');
        $this->assertNotSame($token, $refreshed);
        $this->resetAuth();

        $this->withToken($refreshed)->postJson("api/v1/{$app}/logout")->assertOk();
        $this->resetAuth();

        $this->withToken($refreshed)->getJson("api/v1/{$app}/get_profil")->assertStatus(401);
    }

    /**
     * @test
     * @dataProvider apps
     */
    public function a_customer_token_does_not_open_the_driver_api_and_vice_versa(string $app, string $otpModel)
    {
        $this->createAccount($app);
        $token = $this->verifyOtp($app, $this->sendOtp($app, $otpModel))->json('data.token');
        $otherApp = $app === 'customers' ? 'drivers' : 'customers';

        $this->withToken($token)->getJson("api/v1/{$otherApp}/get_profil")->assertStatus(401);
    }

    /** @test */
    public function an_unknown_customer_can_verify_an_otp_before_registering()
    {
        $otp = $this->sendOtp('customers', CustomerOTP::class);

        $this->verifyOtp('customers', $otp)
            ->assertOk()
            ->assertJsonPath('data', true);
    }

    /** @test */
    public function an_unknown_driver_cannot_log_in()
    {
        $otp = $this->sendOtp('drivers', DriverOtp::class);

        $this->verifyOtp('drivers', $otp)->assertNotFound();
    }

    private function createAccount(string $app): Customer|Driver
    {
        return $app === 'customers'
            ? Customer::create(['name' => 'Client Test', 'phone' => self::PHONE, 'is_active' => true])
            : Driver::factory()->create(['phone' => self::PHONE]);
    }

    private function sendOtp(string $app, string $otpModel): string
    {
        $this->postJson("api/v1/{$app}/send-otp", ['phone' => self::PHONE])->assertOk();

        return (string) $otpModel::where('phone', self::PHONE)->value('otp');
    }

    private function verifyOtp(string $app, string $otp)
    {
        return $this->postJson("api/v1/{$app}/verify-otp", ['phone' => self::PHONE, 'otp' => $otp]);
    }

    /** Les gardes et le singleton JWT mémorisent l'utilisateur et le jeton entre deux requêtes du même test. */
    private function resetAuth(): void
    {
        $this->app['auth']->forgetGuards();
        $this->app['tymon.jwt']->unsetToken();
    }
}
