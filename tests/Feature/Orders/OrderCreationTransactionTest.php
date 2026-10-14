<?php

namespace Tests\Feature\Orders;

use App\Jobs\SendSms;
use App\Models\Commercial;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Service;
use Database\Seeders\TypeEnginModelsSeeder;
use Database\Seeders\TypeEnginsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\Feature\Orders\Aggregats\AggregatOrderTestCase;

class OrderCreationTransactionTest extends AggregatOrderTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([TypeEnginsSeeder::class, TypeEnginModelsSeeder::class]);
    }

    private function courseItem(array $metaData = []): array
    {
        return [
            'service_slug' => Service::COURSE,
            'meta_data' => array_merge([
                'type_engin_slug' => 'camion-benne',
                'engin_model' => '6-roues-8m3',
                'delivery_type_code' => 'EXPRESS',
                'delivery_fees' => 20000,
            ], $metaData),
            'route_points' => [
                $this->destination(['type' => 'source']),
                $this->destination(['type' => 'destination']),
            ],
        ];
    }

    /** @test */
    public function the_commercial_sms_is_queued_instead_of_blocking_the_request()
    {
        Queue::fake();

        Commercial::create(['name' => 'Commercial Test', 'phone' => '2250100000000', 'code' => 'COM1', 'current_balance' => 0, 'is_active' => true]);
        $this->customer->update(['code_commercial' => 'COM1']);

        $this->createOrder($this->courseItem())->assertOk();

        Queue::assertPushed(SendSms::class, fn (SendSms $job) => $job->phoneNumber === '+2250100000000'
            && str_contains($job->message, 'Votre compte a été credité'));
    }

    /** @test */
    public function the_order_is_saved_in_a_single_update()
    {
        $response = $this->createOrder($this->courseItem())->assertOk();

        $order = Order::findOrFail($response->json('data.id'));
        $this->assertSame(Service::COURSE, $order->service_slug);
        $this->assertSame('EXPRESS', $order->delivery_type_code);
        $this->assertTrue($order->is_ride);
        $this->assertEquals(20000, $order->delivery_price);
    }

    /** @test */
    public function an_unexpected_error_rolls_back_the_order()
    {
        OrderItem::created(fn () => throw new RuntimeException('panne'));
        $transactionLevel = DB::transactionLevel();

        $this->withoutExceptionHandling();

        try {
            $this->createOrder($this->courseItem());
            $this->fail('La création aurait dû échouer.');
        } catch (RuntimeException $e) {
            $this->assertSame('panne', $e->getMessage());
        }

        // La transaction ouverte par store() est refermée, et la commande déjà insérée annulée
        $this->assertSame($transactionLevel, DB::transactionLevel());
        $this->assertDatabaseCount('orders', 0);
    }
}
