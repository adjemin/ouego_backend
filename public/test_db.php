<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    $results = DB::select('SELECT 1');
    var_dump($results);
} catch (Exception $e) {
    echo "Erreur de connexion : " . $e->getMessage();
}
