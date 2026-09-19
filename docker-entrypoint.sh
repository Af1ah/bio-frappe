#!/bin/sh
set -e

# Ensure storage directories exist and have proper permissions
mkdir -p /var/www/html/storage/framework/cache
mkdir -p /var/www/html/storage/framework/sessions
mkdir -p /var/www/html/storage/framework/views
mkdir -p /var/www/html/storage/logs
rm -f /var/www/html/bootstrap/cache/packages.php /var/www/html/bootstrap/cache/services.php /var/www/html/bootstrap/cache/config.php
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true

# If running inside Docker with mounted .env, ensure DB_HOST points to container network
if [ -f /var/www/html/.env ] && [ -n "$DB_HOST" ]; then
    sed -i "s|^DB_HOST=127.0.0.1|DB_HOST=${DB_HOST}|g" /var/www/html/.env 2>/dev/null || true
    sed -i "s|^DB_HOST=localhost|DB_HOST=${DB_HOST}|g" /var/www/html/.env 2>/dev/null || true
fi

# If custom command was provided to docker run / exec, run it directly
if [ "$#" -gt 0 ] && [ "$1" != "php" -o "$2" != "artisan" -o "$3" != "serve" ]; then
    exec "$@"
fi

# Multi-role container execution
case "${APP_ROLE:-app}" in
    worker|queue)
        echo "=> Starting Bio-Frappe Queue Worker..."
        exec php artisan queue:work --sleep=2 --tries=3 --timeout=120
        ;;
    scheduler)
        echo "=> Starting Bio-Frappe Scheduler Daemon..."
        exec php artisan schedule:work
        ;;
    supervisor|all)
        echo "=> Starting Bio-Frappe All-in-One Daemon (Web + Worker + Scheduler)..."
        exec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/supervisord.conf
        ;;
    *)
        echo "=> Starting Bio-Frappe Web Server on port ${APP_PORT:-8000}..."
        exec php artisan serve --host=0.0.0.0 --port="${APP_PORT:-8000}"
        ;;
esac
