<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Comptes de test (OTP fixe)
    |--------------------------------------------------------------------------
    |
    | Numéros (format dialing_code + phone_number, ex: 2250999000001) pour
    | lesquels aucun SMS n'est envoyé et l'OTP est toujours `test_code`.
    | Mettre OTP_TEST_PHONES="" pour désactiver le bypass.
    |
    */

    'test_phones' => array_values(array_filter(array_map('trim', explode(',', env('OTP_TEST_PHONES', '2250999000001,2250999000002'))))),

    'test_code' => env('OTP_TEST_CODE', '123456'),

];
