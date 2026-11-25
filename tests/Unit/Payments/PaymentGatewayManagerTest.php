<?php

namespace Tests\Unit\Payments;

use App\Models\Setting;
use App\Payments\Exceptions\PaymentProviderUnavailable;
use App\Payments\PaymentGatewayManager;
use Tests\Support\FakePaymentGateway;
use Tests\TestCase;

/**
 * Un seul opérateur actif, lu dans settings ; un paiement existant reste traité par son opérateur d'origine.
 */
class PaymentGatewayManagerTest extends TestCase
{
    private function manager(): PaymentGatewayManager
    {
        return app(PaymentGatewayManager::class);
    }

    /** @test */
    public function active_throws_when_no_provider_is_set()
    {
        FakePaymentGateway::install(activate: false);

        $this->expectException(PaymentProviderUnavailable::class);

        $this->manager()->active();
    }

    /** @test */
    public function active_throws_for_an_unknown_provider()
    {
        Setting::set('PAYMENT_ACTIVE_PROVIDER', 'inconnu');

        $this->expectException(PaymentProviderUnavailable::class);

        $this->manager()->active();
    }

    /** @test */
    public function active_throws_when_the_provider_is_not_configured()
    {
        FakePaymentGateway::install()->configured = false;

        $this->expectException(PaymentProviderUnavailable::class);

        $this->manager()->active();
    }

    /** @test */
    public function active_returns_the_configured_provider()
    {
        $fake = FakePaymentGateway::install();

        $this->assertSame($fake, $this->manager()->active());
        $this->assertSame('fake', $this->manager()->activeCode());
    }

    /** @test */
    public function driver_resolves_a_provider_even_when_it_is_not_active()
    {
        $fake = FakePaymentGateway::install(activate: false);

        $this->assertSame($fake, $this->manager()->driver('fake'));
    }

    /** @test */
    public function driver_throws_for_an_unknown_provider()
    {
        $this->expectException(PaymentProviderUnavailable::class);

        $this->manager()->driver('inconnu');
    }
}
