#!/bin/sh
set -e

# Configuration comes from the container environment (root .env through compose.yml),
# so no api/.env file is created here.

if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist
fi

# The acquirer mock has no database, so only the API runs the migrations.
if [ "${ACQUIRER_MOCK_ENABLED:-false}" != "true" ]; then
    php artisan migrate --force
fi

exec "$@"
