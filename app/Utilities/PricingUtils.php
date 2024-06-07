<?php

namespace App\Utilities;

use App\Models\Setting;

class PricingUtils{


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
