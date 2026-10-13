#!/bin/sh
set -e

php bin/console doctrine:migrations:migrate --no-interaction
php bin/console cache:clear

exec "$@"
