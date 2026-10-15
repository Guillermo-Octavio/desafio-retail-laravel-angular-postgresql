#!/bin/sh
set -e

# Configuration comes from the container environment (root .env through compose.yml),
# so no api/.env file is created here.

if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist
fi

php artisan migrate --force

exec "$@"
