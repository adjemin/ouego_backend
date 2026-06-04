#!/bin/bash
# Make sure this file has executable permissions, run `chmod +x railway/init-app.sh`

# Exit the script if any command fails
set -e

# Create the PostgreSQL schema if it doesn't exist.
# This is required because DB_SCHEMA=ouego_database: Laravel checks for the
# migrations table in that schema, and if it doesn't exist the table check
# returns false even though public.migrations already exists, causing a
# "duplicate table" error on migrate.
php << 'PHPEOF'
<?php
$host = getenv('DB_HOST');
$port = getenv('DB_PORT') ?: 5432;
$db   = getenv('DB_DATABASE');
$user = getenv('DB_USERNAME');
$pass = getenv('DB_PASSWORD');
$schema = getenv('DB_SCHEMA') ?: 'public';
try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$db", $user, $pass);
    $pdo->exec("CREATE SCHEMA IF NOT EXISTS \"$schema\"");
    echo "Schema '$schema' is ready.\n";
} catch (Exception $e) {
    echo "Schema note: " . $e->getMessage() . "\n";
}
PHPEOF

# Run migrations
php artisan migrate --force

# Clear cache
php artisan optimize:clear

# Cache the various components of the Laravel application
php artisan config:cache
php artisan event:cache
php artisan route:cache
php artisan view:cache