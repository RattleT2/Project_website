#!/bin/sh
set -e

echo "Starting deployment setup..."

# Wait for MySQL to be ready
if [ -n "$DB_HOST" ]; then
    echo "Waiting for MySQL at $DB_HOST:$DB_PORT..."
    while ! nc -z $DB_HOST ${DB_PORT:-3306}; do
        sleep 2
    done
    echo "MySQL is ready!"
fi

# Ensure storage link exists
php artisan storage:link --quiet || true

# Optimize cache in production
if [ "$APP_ENV" = "production" ]; then
    echo "Caching configuration and routes..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

# Run database migrations
echo "Running migrations..."
php artisan migrate --force || true

# Ensure proper permissions
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

# Start PHP-FPM in background
echo "Starting PHP-FPM..."
php-fpm -D

# Start Nginx in foreground
echo "Starting Nginx..."
exec nginx -g 'daemon off;'
