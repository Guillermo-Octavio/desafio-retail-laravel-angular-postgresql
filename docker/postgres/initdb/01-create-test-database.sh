#!/bin/sh
# Creates the database used by the test suite, separate from the development database.
# PostgreSQL only runs this script when it initializes an empty volume.
set -eu

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" \
    -c "CREATE DATABASE payments_test OWNER \"$POSTGRES_USER\";"
