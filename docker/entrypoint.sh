#!/bin/sh
set -eu

if [ ! -f vendor/autoload.php ]; then
    composer install
else
    echo "Composer dependencies already installed."
fi

if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
    test -f database/database.sqlite || touch database/database.sqlite
fi

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --seed --force
else
    echo "Skipping migrations and seeders because RUN_MIGRATIONS is not true."
fi

php artisan serve --host=0.0.0.0 --port=8000
