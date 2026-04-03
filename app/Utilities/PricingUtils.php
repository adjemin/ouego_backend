<?php

namespace App\Utilities;

use App\Models\DeliveryType;
use App\Models\Setting;
use Illuminate\Support\Facades\Mail;

class PricingUtils{


    public static function transportGravier($distance, $quantity, $delivery_type){

        //DISTANCE DE BASE (KM)
        $distance_de_base = doubleval(Setting::get('GRAVIER_DISTANCE_DE_BASE'));

        //QUANTITE DE BASE (T)
        $quantite_de_base = doubleval(Setting::get('GRAVIER_QUANTITE_DE_BASE'));

        //PRIX DE BASE (minimum)
        $prix_de_base = doubleval(Setting::get('GRAVIER_PRIX_DE_BASE'));

        //PRIX KILOMETRE (>45 km)
        $prix_kilometre = doubleval(Setting::get('GRAVIER_PRIX_KILOMETRE'));

        //PRIX TONNAGE (> 20 T)
        $prix_tonnage = doubleval(Setting::get('GRAVIER_PRIX_TONNAGE'));

        //FRAIS_DE_ROUTE
        $frais_route = doubleval(Setting::get('GRAVIER_FRAIS_DE_ROUTE'));

        //COMMISSION OUEGO (à titre indicatif)
        $commission_ouego = doubleval(Setting::get('GRAVIER_COMMISSION_OUEGO'));

        $amount = $prix_de_base  + (MAX(0, ($distance - $distance_de_base)) * $prix_kilometre) + (MAX(0, ($quantity - $quantite_de_base)) * $prix_tonnage)  + $frais_route + $commission_ouego;

        $amount =  self::round_up($amount, 100);

        $amount = self::applyDeliveryPricing($amount, $delivery_type);

        return self::round_up($amount, 100);

    }

    public static function transportSable($distance, $delivery_type){

        //DISTANCE DE BASE (KM)
        $distance_de_base = doubleval(Setting::get('SABLE_DISTANCE_DE_BASE'));


        //PRIX DE BASE (minimum)
        $prix_de_base = doubleval(Setting::get('SABLE_PRIX_DE_BASE'));

        //PRIX KILOMETRE (>45 km)
        $prix_kilometre = doubleval(Setting::get('SABLE_PRIX_KILOMETRE'));

        //FRAIS_DE_ROUTE
        $frais_route = doubleval(Setting::get('SABLE_FRAIS_DE_ROUTE'));

        //COMMISSION OUEGO (à titre indicatif)
        $commission_ouego = doubleval(Setting::get('SABLE_COMMISSION_OUEGO'));

        $amount =  $prix_de_base + (MAX(0, ($distance - $distance_de_base)) * $prix_kilometre) + $frais_route + $commission_ouego;

        $amount =  self::round_up($amount, 100);

        $amount = self::applyDeliveryPricing($amount, $delivery_type);

        return self::round_up($amount, 100);

    }

    public static function transportCourse($distance, $typeEnginModel, $delivery_type){


        //FORMULE DEVELOPPEE 1
        //MAX(PRIX BASE;D1 x PRIX_KM1) + D2 x PRIX_KM2  + D3 x PRIX_KM3 + CHARGEMENT + FRAIS DE ROUTE

        //FORMULE DETAILLEE
        //MAX (PRIX BASE ; MIN (DISTANCE_1 ; DISTANCE_TRAJET) x PRIX_KM1)) +
        //MAX (0 ; MIN (DISTANCE_2 - DISTANCE_1 ; MAX(DISTANCE_TRAJET - DISTANCE_1 ; 0))) x PRIX_KM2 +
        //MAX (0 ; DISTANCE_TRAJET - DISTANCE_2) x PRIX_KM3 +
        //CHARGEMENT + FRAIS DE ROUTE

        $prix_base = doubleval($typeEnginModel->ride_base_pricing);

        $frais_route = doubleval(Setting::get('FRAIS_ROUTE'));

        $commission_course = doubleval(Setting::get('COURSE_COMMISSION_OUEGO'));

        $initial_distance = $distance;


        $t1 = max($prix_base, min($typeEnginModel->slice_1_max_distance, $initial_distance) * $typeEnginModel->slice_1_pricing);
        $t2 = max(0, min(($typeEnginModel->slice_2_max_distance - $typeEnginModel->slice_1_max_distance), max(0, $initial_distance - $typeEnginModel->slice_1_max_distance))) * $typeEnginModel->slice_2_pricing;
        $t3 = max(0,$initial_distance - $typeEnginModel->slice_2_max_distance) * $typeEnginModel->slice_3_pricing;


        $chargement = $typeEnginModel->manutention_pricing;

        $amount = $t1 + $t2 + $t3 + $chargement + $frais_route + $commission_course;

        $amount =  self::round_up($amount, 100);

        $amount = self::applyDeliveryPricing($amount, $delivery_type);

        return self::round_up($amount, 100);

    }

    public static function transport($distance, $delivery_type){

        $prix_carburant = doubleval(Setting::get('PRIX_CARBURANT'));
        $conso_litre = doubleval(Setting::get('CONSO_LITRE'));

        $total_conso = $conso_litre * $prix_carburant;

        $marge_chauffeur_course_par_km = doubleval(Setting::get('MARGE_CHAUFFEUR_COURSE'));

        $commission_course = doubleval(Setting::get('TRANSPORT_COMMISSION_OUEGO'));

        $marge_brute = $marge_chauffeur_course_par_km + $commission_course;

        $prix_transport_net = ($total_conso + $marge_brute ) * $distance;

        $frais_route = doubleval(Setting::get('FRAIS_ROUTE'));

        $taxe = doubleval(Setting::get('TAXE'));

        $taxe_amount = $prix_transport_net * $taxe;

        $amount =  $prix_transport_net + $frais_route + $taxe_amount;

        $amount =  self::round_up($amount, 100);

        $amount = self::applyDeliveryPricing($amount, $delivery_type);

        return self::round_up($amount, 100);

    }

    public static function applyDeliveryPricing(float $amount, string $delivery_type): float
    {
        $config = DeliveryType::where('slug', $delivery_type)->first();

        if (!$config) {
            return $amount;
        }

        return match($config->pricing_operator) {
            'add'              => $amount + $config->pricing_value,
            'subtract'         => $amount - $config->pricing_value,
            'multiply'         => $amount * $config->pricing_value,
            'divide'           => $config->pricing_value != 0 ? $amount / $config->pricing_value : $amount,
            'add_percent'      => $amount + $amount * ($config->pricing_value / 100),
            'subtract_percent' => $amount - $amount * ($config->pricing_value / 100),
            default            => $amount,
        };
    }

    public static function round_up($num, $mul) {
        return ceil($num / $mul) * $mul;
    }

}
