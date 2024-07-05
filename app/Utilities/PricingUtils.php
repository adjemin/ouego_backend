<?php

namespace App\Utilities;

use App\Models\Setting;

class PricingUtils{


    public static function transportGravier($distance){

        //DISTANCE DE BASE (KM)
        $distance_de_base = doubleval(Setting::get('DISTANCE_DE_BASE'));

        //QUANTITE DE BASE (T)
        $quantite_de_base = doubleval(Setting::get('QUANTITE_DE_BASE'));

        //PRIX DE BASE (minimum)
        $prix_de_base = doubleval(Setting::get('PRIX_DE_BASE'));

        //PRIX KILOMETRE (>45 km)
        $prix_kilometre = doubleval(Setting::get('PRIX_KILOMETRE'));

        //PRIX TONNAGE (> 20 T)
        $prix_tonnage = doubleval(Setting::get('PRIX_TONNAGE'));

        //FRAIS_DE_ROUTE
        $prix_tonnage = doubleval(Setting::get('FRAIS_DE_ROUTE'));

        //COMMISSION OUEGO (à titre indicatif)
        $commission_ouego = doubleval(Setting::get('COMMISSION_OUEGO'));




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
