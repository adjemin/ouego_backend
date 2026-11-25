<?php

namespace Tests\Feature\Payments;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentRefund;
use Illuminate\Database\QueryException;
use Tests\TestCase;

/**
 * Socle des paiements opérateur : références uniques, un seul remboursement par paiement,
 * et règle « en ligne = tout sauf cash ».
 */
class PaymentModelTest extends TestCase
{
    /** @test */
    public function generated_references_are_unique_and_prefixed()
    {
        $references = collect(range(1, 100))->map(fn () => Payment::generateReference());

        $this->assertCount(100, $references->unique());
        $references->each(function (string $reference) {
            $this->assertStringStartsWith('OUEGO-', $reference);
            $this->assertGreaterThanOrEqual(5, strlen($reference));
            $this->assertLessThanOrEqual(100, strlen($reference));
        });
    }

    /** @test */
    public function duplicate_payment_reference_is_rejected()
    {
        Payment::create(['payment_reference' => 'OUEGO-DUP', 'amount' => 100]);

        $this->expectException(QueryException::class);

        Payment::create(['payment_reference' => 'OUEGO-DUP', 'amount' => 100]);
    }

    /** @test */
    public function only_one_refund_per_payment()
    {
        $payment = Payment::create(['payment_reference' => Payment::generateReference(), 'amount' => 100]);
        $attributes = [
            'payment_id' => $payment->id,
            'provider' => 'jeko',
            'amount' => 100,
            'status' => PaymentRefund::PENDING,
        ];

        PaymentRefund::create($attributes + ['reference' => 'RF-1']);

        $this->expectException(QueryException::class);

        PaymentRefund::create($attributes + ['reference' => 'RF-2']);
    }

    /** @test */
    public function is_paid_online_is_true_for_everything_but_cash()
    {
        $this->assertFalse((new Order(['payment_method_code' => 'cash']))->isPaidOnline());
        $this->assertTrue((new Order(['payment_method_code' => 'online']))->isPaidOnline());
        $this->assertTrue((new Order(['payment_method_code' => 'mobile-money']))->isPaidOnline());
    }
}
