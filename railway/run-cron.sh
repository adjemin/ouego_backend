#!/bin/bash

set -e

echo "Starting Laravel scheduler..."

while true
do
    echo "[$(date)] Running scheduler..."
    
    php artisan schedule:run --verbose --no-interaction || true
    
    sleep 60
done