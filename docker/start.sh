#!/bin/bash
set -e

# Decode Firebase credentials from base64 env var if provided
if [ -n "$FIREBASE_CREDENTIALS_BASE64" ]; then
    echo "Decoding Firebase credentials..."
    mkdir -p /var/www/html/storage/app
    echo "$FIREBASE_CREDENTIALS_BASE64" | base64 -d > /var/www/html/storage/app/firebase-credentials.json
    export GOOGLE_APPLICATION_CREDENTIALS=/var/www/html/storage/app/firebase-credentials.json
    export FIREBASE_CREDENTIALS=/var/www/html/storage/app/firebase-credentials.json
fi

# Set correct permissions
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Run migrations
echo "Running migrations..."
php /var/www/html/artisan migrate --force

# Create storage symlink
php /var/www/html/artisan storage:link --force 2>/dev/null || true

# Cache config for production
php /var/www/html/artisan config:cache
php /var/www/html/artisan route:cache
php /var/www/html/artisan view:cache

echo "Starting services..."
exec supervisord -c /etc/supervisor/conf.d/supervisord.conf
