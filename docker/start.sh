#!/bin/sh
set -e

[ -f .env ] || cp .env.example .env
composer install --no-interaction --prefer-dist
grep -q '^APP_KEY=base64' .env || php artisan key:generate --force

# Wait for MySQL, then migrate.
until php artisan migrate --force; do
  echo "Waiting for MySQL..."
  sleep 3
done

php artisan storage:link 2>/dev/null || true
exec php -d upload_max_filesize=12M -d post_max_size=60M -d max_execution_time=180 artisan serve --host=0.0.0.0 --port=8000
