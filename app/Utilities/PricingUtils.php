<?php

namespace App\Utilities;

use App\Models\Setting;

class PricingUtils{


    public static function transportGravier($distance, $quantity){

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
        //$commission_ouego = doubleval(Setting::get('GRAVIER_COMMISSION_OUEGO'));


        $difference_distance = $distance - $distance_de_base;

        if($difference_distance <0){
            $difference_distance = 0;
        }

        $difference_quantity = $quantity - $quantite_de_base;
        if($difference_quantity < 0){
            $difference_quantity = 0;
        }

        $amount = $prix_de_base  + ($difference_distance * $prix_kilometre) + ($difference_quantity * $prix_tonnage)  + $frais_route;

        return self::round_up($amount, 100) ;

    }

    public static function transportSable($distance){

        //DISTANCE DE BASE (KM)
        $distance_de_base = doubleval(Setting::get('SABLE_DISTANCE_DE_BASE'));


        //PRIX DE BASE (minimum)
        $prix_de_base = doubleval(Setting::get('SABLE_PRIX_DE_BASE'));

        //PRIX KILOMETRE (>45 km)
        $prix_kilometre = doubleval(Setting::get('SABLE_PRIX_KILOMETRE'));


        //FRAIS_DE_ROUTE
        $frais_route = doubleval(Setting::get('SABLE_FRAIS_DE_ROUTE'));

        //COMMISSION OUEGO (à titre indicatif)
       // $commission_ouego = doubleval(Setting::get('SABLE_COMMISSION_OUEGO'));

       $difference_distance = $distance - $distance_de_base;

       if($difference_distance <0){
           $difference_distance = 0;
       }


        $amount =  $prix_de_base + ($difference_distance * $prix_kilometre) + $frais_route;

        return self::round_up($amount, 100) ;

    }

    public static function transportCourse($distance, $typeEnginModel){


        //MAX(PRIX BASE;D1 x PRIX_KM1) + D2 x PRIX_KM2  + D3 x PRIX_KM3 + CHARGEMENT + FRAIS DE ROUTE

        //MAX (PRIX BASE ; MIN (DISTANCE_1 ; DISTANCE_TRAJET) x PRIX_KM1)) +
        //MAX (0 ; MIN (DISTANCE_2 - DISTANCE_1 ; MAX(DISTANCE_TRAJET - DISTANCE_1 ; 0))) x PRIX_KM2 +
        //MAX (0 ; DISTANCE_TRAJET - DISTANCE_2) x PRIX_KM3 +
        //CHARGEMENT + FRAIS DE ROUTE

        $prix_base = doubleval($typeEnginModel->ride_base_pricing);

        $frais_route = doubleval(Setting::get('FRAIS_ROUTE'));

        $initial_distance = $distance;

        $distance1 = 0;

        if($distance > $typeEnginModel->slice_1_max_distance ){
            $distance1 = $typeEnginModel->slice_1_max_distance;
            $distance = $distance - $typeEnginModel->slice_1_max_distance;
        }else{
            $distance1 = $distance;
            $distance = 0;
        }

        $distance2 = 0;

        if($distance > 0 && $distance > $typeEnginModel->slice_2_max_distance ){
            $distance2 = $typeEnginModel->slice_2_max_distance;
            $distance = $distance - $typeEnginModel->slice_2_max_distance;

        }else{
            $distance2 = $distance;
            $distance = 0;
        }

        $distance3 = $distance;

        $t1 = $distance1 * $typeEnginModel->slice_1_pricing;
        $t2 = $distance2 * $typeEnginModel->slice_2_pricing;
        $t3 = $distance3 * $typeEnginModel->slice_3_pricing;

        $chargement = $typeEnginModel->manutention_pricing;

        $amount = max($prix_base, $t1) + $t2 + $t3 + $chargement + $frais_route;

        return self::round_up($amount, 100) ;

    }

    public static function transport($distance){

        $prix_carburant = doubleval(Setting::get('PRIX_CARBURANT'));
        $conso_litre = doubleval(Setting::get('CONSO_LITRE'));

        $total_conso = $conso_litre * $prix_carburant;

        $marge_chauffeur_course_par_km = doubleval(Setting::get('MARGE_CHAUFFEUR_COURSE'));

        $commission_course = doubleval(Setting::get('COMMISSION_COURSE'));

        $marge_brute = $marge_chauffeur_course_par_km + $commission_course;

        $prix_transport_net = ($total_conso + $marge_brute ) * $distance;

        $frais_route = doubleval(Setting::get('FRAIS_ROUTE'));

        $taxe = doubleval(Setting::get('TAXE'));

        $taxe_amount = $prix_transport_net * $taxe;

        $amount =  $prix_transport_net + $frais_route + $taxe_amount;

        return self::round_up($amount, 100) ;

    }

    public static function round_up($num, $mul) {
        return ceil($num / $mul) * $mul;
    }

}
