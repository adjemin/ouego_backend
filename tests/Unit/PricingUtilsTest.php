<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Utilities\PricingUtils;
use App\Models\Setting;
use Illuminate\Foundation\Testing\WithFaker;

class PricingUtilsTest extends TestCase
{
    use WithFaker;


    protected function setUp(): void
    {
        parent::setUp();
        
        Setting::updateOrCreate(['name' => 'GRAVIER_DISTANCE_DE_BASE'], ['value' => '45']);
        Setting::updateOrCreate(['name' => 'GRAVIER_QUANTITE_DE_BASE'], ['value' => '20']);
        Setting::updateOrCreate(['name' => 'GRAVIER_PRIX_DE_BASE'], ['value' => '55000']);
        Setting::updateOrCreate(['name' => 'GRAVIER_PRIX_KILOMETRE'], ['value' => '1000']);
        Setting::updateOrCreate(['name' => 'GRAVIER_PRIX_TONNAGE'], ['value' => '1000']);
        Setting::updateOrCreate(['name' => 'GRAVIER_FRAIS_DE_ROUTE'], ['value' => '10000']);
        
        Setting::updateOrCreate(['name' => 'SABLE_DISTANCE_DE_BASE'], ['value' => '5']);
        Setting::updateOrCreate(['name' => 'SABLE_PRIX_DE_BASE'], ['value' => '20000']);
        Setting::updateOrCreate(['name' => 'SABLE_PRIX_KILOMETRE'], ['value' => '1000']);
        Setting::updateOrCreate(['name' => 'SABLE_FRAIS_DE_ROUTE'], ['value' => '0']);
        
        Setting::updateOrCreate(['name' => 'FRAIS_ROUTE'], ['value' => '3000']);
        Setting::updateOrCreate(['name' => 'PRIX_CARBURANT'], ['value' => '650']);
        Setting::updateOrCreate(['name' => 'CONSO_LITRE'], ['value' => '0.15']);
        Setting::updateOrCreate(['name' => 'MARGE_CHAUFFEUR_COURSE'], ['value' => '200']);
        Setting::updateOrCreate(['name' => 'COMMISSION_COURSE'], ['value' => '100']);
        Setting::updateOrCreate(['name' => 'TAXE'], ['value' => '0.18']);
    }

    /** @test */
    public function test_round_up_function()
    {
        $this->assertEquals(100, PricingUtils::round_up(50, 100));
        $this->assertEquals(100, PricingUtils::round_up(1, 100));
        $this->assertEquals(200, PricingUtils::round_up(101, 100));
        $this->assertEquals(500, PricingUtils::round_up(450, 100));
        $this->assertEquals(1000, PricingUtils::round_up(1000, 100));
        $this->assertEquals(0, PricingUtils::round_up(0, 100));
    }

    /** @test */
    public function test_transport_gravier_base_price()
    {
        $distance = 45;
        $quantity = 20;
        $delivery_type = "EXPRESS";
        
        $result = PricingUtils::transportGravier($distance, $quantity, $delivery_type);
        
        $expected = 65000;
        $this->assertEquals($expected, $result);
    }

    /** @test */
    public function test_transport_gravier_with_extra_distance()
    {
        $distance = 49.8;
        $quantity = 20;
        $delivery_type = "EXPRESS";
        
        $result = PricingUtils::transportGravier($distance, $quantity, $delivery_type);
        
        $expected = 69800;
        $this->assertEquals($expected, $result);
    }

    /** @test */
    public function test_transport_gravier_with_extra_quantity()
    {
        $distance = 45;
        $quantity = 35;
        $delivery_type = "EXPRESS";
        
        $result = PricingUtils::transportGravier($distance, $quantity, $delivery_type);
        
        $expected = 80000;
        $this->assertEquals($expected, $result);
    }

    /** @test */
    public function test_transport_gravier_en_journee()
    {
        $distance = 45;
        $quantity = 20;
        $delivery_type = "en-journee";
        
        $result = PricingUtils::transportGravier($distance, $quantity, $delivery_type);
        
        $expected = 32500;
        $this->assertEquals($expected, $result);
    }

    /** @test */
    public function test_transport_gravier_de_nuit()
    {
        $distance = 45;
        $quantity = 20;
        $delivery_type = "de-nuit";
        
        $result = PricingUtils::transportGravier($distance, $quantity, $delivery_type);
        
        $expected = 162500;
        $this->assertEquals($expected, $result);
    }

    /** @test */
    public function test_transport_gravier_en_semaine()
    {
        $distance = 45;
        $quantity = 20;
        $delivery_type = "en-semaine";
        
        $result = PricingUtils::transportGravier($distance, $quantity, $delivery_type);
        
        $expected = 21700;
        $this->assertEquals($expected, $result);
    }

    /** @test */
    public function test_transport_sable_base_price()
    {
        $distance = 5;
        $delivery_type = "EXPRESS";
        
        $result = PricingUtils::transportSable($distance, $delivery_type);
        
        $expected = 20000;
        $this->assertEquals($expected, $result);
    }

    /** @test */
    public function test_transport_sable_with_extra_distance()
    {
        $distance = 10;
        $delivery_type = "EXPRESS";
        
        $result = PricingUtils::transportSable($distance, $delivery_type);
        
        $expected = 25000;
        $this->assertEquals($expected, $result);
    }

    /** @test */
    public function test_transport_sable_en_journee()
    {
        $distance = 5;
        $delivery_type = "en-journee";
        
        $result = PricingUtils::transportSable($distance, $delivery_type);
        
        $expected = 10000;
        $this->assertEquals($expected, $result);
    }

    /** @test */
    public function test_transport_sable_de_nuit()
    {
        $distance = 5;
        $delivery_type = "de-nuit";
        
        $result = PricingUtils::transportSable($distance, $delivery_type);
        
        $expected = 50000;
        $this->assertEquals($expected, $result);
    }

    /** @test */
    public function test_transport_sable_en_semaine()
    {
        $distance = 5;
        $delivery_type = "en-semaine";
        
        $result = PricingUtils::transportSable($distance, $delivery_type);
        
        $expected = 6700;
        $this->assertEquals($expected, $result);
    }

    /** @test */
    public function test_transport_course_with_mock_engine()
    {
        $distance = 5;
        $delivery_type = "EXPRESS";
        
        $mockEngine = (object) [
            'ride_base_pricing' => 20000,
            'slice_1_max_distance' => 5,
            'slice_1_pricing' => 1500,
            'slice_2_max_distance' => 20,
            'slice_2_pricing' => 1000,
            'slice_3_pricing' => 500,
            'manutention_pricing' => 5000
        ];
        
        $result = PricingUtils::transportCourse($distance, $mockEngine, $delivery_type);

        
        $expected = 20000 + 5000 + 3000;
        $this->assertEquals($expected, $result);
    }

    /** @test */
    public function test_transport_course_short_distance()
    {
        $distance = 40;
        $delivery_type = "EXPRESS";
        
       $mockEngine = (object) [
            'ride_base_pricing' => 20000,
            'slice_1_max_distance' => 5,
            'slice_1_pricing' => 1500,
            'slice_2_max_distance' => 20,
            'slice_2_pricing' => 1000,
            'slice_3_pricing' => 500,
            'manutention_pricing' => 5000
        ];
        
        $result = PricingUtils::transportCourse($distance, $mockEngine, $delivery_type);

        $t1 = max(20000, min(5, 40) * 1500);
        $t2 = max(0, min((20 - 5), max(0, 20 - 5))) * 1000;
        $t3 = max(0, $initial_distance - 25) * $typeEnginModel->slice_3_pricing;
        
        $expected = $t1;
        $this->assertEquals($expected, $result);
    }

    /** @test */
    public function test_transport_course_en_journee()
    {
        $distance = 25;
        $delivery_type = "en-journee";
        
        $mockEngine = (object) [
            'ride_base_pricing' => 20000,
            'slice_1_max_distance' => 5,
            'slice_1_pricing' => 400,
            'slice_2_max_distance' => 40,
            'slice_2_pricing' => 300,
            'slice_3_pricing' => 200,
            'manutention_pricing' => 1500
        ];
        
        $result = PricingUtils::transportCourse($distance, $mockEngine, $delivery_type);
        
        $t1 = max(20000, min(5, $initial_distance) * $typeEnginModel->slice_1_pricing);
        $expected = 9900;
        $this->assertEquals($expected, $result);
    }

    /** @test */
    public function test_transport_basic_calculation()
    {
        $distance = 50;
        $delivery_type = "EXPRESS";
        
        $result = PricingUtils::transport($distance, $delivery_type);
        
        $prix_carburant = 650;
        $conso_litre = 0.15;
        $total_conso = $conso_litre * $prix_carburant;
        $marge_brute = 200 + 100;
        $prix_transport_net = ($total_conso + $marge_brute) * $distance;
        $frais_route = 3000;
        $taxe_amount = $prix_transport_net * 0.18;
        $expected_before_round = $prix_transport_net + $frais_route + $taxe_amount;
        $expected = ceil($expected_before_round / 100) * 100;
        
        $this->assertEquals($expected, $result);
    }

    /** @test */
    public function test_transport_en_journee()
    {
        $distance = 30;
        $delivery_type = "en-journee";
        
        $result = PricingUtils::transport($distance, $delivery_type);
        
        $prix_carburant = 650;
        $conso_litre = 0.15;
        $total_conso = $conso_litre * $prix_carburant;
        $marge_brute = 200 + 100;
        $prix_transport_net = ($total_conso + $marge_brute) * $distance;
        $frais_route = 3000;
        $taxe_amount = $prix_transport_net * 0.18;
        $amount_before_delivery = $prix_transport_net + $frais_route + $taxe_amount;
        $amount_after_delivery = $amount_before_delivery / 2;
        $expected = ceil($amount_after_delivery / 100) * 100;
        
        $this->assertEquals($expected, $result);
    }

    /** @test */
    public function test_transport_de_nuit()
    {
        $distance = 25;
        $delivery_type = "de-nuit";
        
        $result = PricingUtils::transport($distance, $delivery_type);
        
        $prix_carburant = 650;
        $conso_litre = 0.15;
        $total_conso = $conso_litre * $prix_carburant;
        $marge_brute = 200 + 100;
        $prix_transport_net = ($total_conso + $marge_brute) * $distance;
        $frais_route = 3000;
        $taxe_amount = $prix_transport_net * 0.18;
        $amount_before_delivery = $prix_transport_net + $frais_route + $taxe_amount;
        $amount_after_delivery = $amount_before_delivery + $amount_before_delivery * 1.5;
        $expected = ceil($amount_after_delivery / 100) * 100;
        
        $this->assertEquals($expected, $result);
    }

    /** @test */
    public function test_transport_en_semaine()
    {
        $distance = 40;
        $delivery_type = "en-semaine";
        
        $result = PricingUtils::transport($distance, $delivery_type);
        
        $prix_carburant = 650;
        $conso_litre = 0.15;
        $total_conso = $conso_litre * $prix_carburant;
        $marge_brute = 200 + 100;
        $prix_transport_net = ($total_conso + $marge_brute) * $distance;
        $frais_route = 3000;
        $taxe_amount = $prix_transport_net * 0.18;
        $amount_before_delivery = $prix_transport_net + $frais_route + $taxe_amount;
        $amount_after_delivery = $amount_before_delivery / 3;
        $expected = ceil($amount_after_delivery / 100) * 100;
        
        $this->assertEquals($expected, $result);
    }

    /** @test */
    public function test_transport_unknown_delivery_type()
    {
        $distance = 20;
        $delivery_type = "UNKNOWN";
        
        $result = PricingUtils::transport($distance, $delivery_type);
        
        $prix_carburant = 650;
        $conso_litre = 0.15;
        $total_conso = $conso_litre * $prix_carburant;
        $marge_brute = 200 + 100;
        $prix_transport_net = ($total_conso + $marge_brute) * $distance;
        $frais_route = 3000;
        $taxe_amount = $prix_transport_net * 0.18;
        $expected_before_round = $prix_transport_net + $frais_route + $taxe_amount;
        $expected = ceil($expected_before_round / 100) * 100;
        
        $this->assertEquals($expected, $result);
    }

    /** @test */
    public function test_negative_distance_handling_in_gravier()
    {
        $distance = 0;
        $quantity = 10;
        $delivery_type = "EXPRESS";
        
        $result = PricingUtils::transportGravier($distance, $quantity, $delivery_type);
        
        $this->assertGreaterThan(0, $result);
    }

    /** @test */
    public function test_negative_quantity_handling_in_gravier()
    {
        $distance = 50;
        $quantity = 0;
        $delivery_type = "EXPRESS";
        
        $result = PricingUtils::transportGravier($distance, $quantity, $delivery_type);
        
        $this->assertGreaterThan(0, $result);
    }

    /** @test */
    public function test_zero_distance_in_sable()
    {
        $distance = 0;
        $delivery_type = "EXPRESS";
        
        $result = PricingUtils::transportSable($distance, $delivery_type);
        
        $this->assertGreaterThan(0, $result);
    }

    /** @test */
    public function test_zero_distance_in_transport()
    {
        $distance = 0;
        $delivery_type = "EXPRESS";
        
        $result = PricingUtils::transport($distance, $delivery_type);
        
        $this->assertGreaterThan(0, $result);
    }
}