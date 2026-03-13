#!/bin/sh
set -e

php artisan config:cache || true
php artisan schedule:run 