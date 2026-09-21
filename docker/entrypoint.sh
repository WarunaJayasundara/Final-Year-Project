#!/bin/sh
set -e

# Most free PaaS hosts (Render, Railway, Fly.io) inject the port to bind via
# $PORT rather than always using 80 - remap Apache's listen port at container
# start (not build time), since $PORT isn't known until the container runs.
if [ -n "$PORT" ] && [ "$PORT" != "80" ]; then
  sed -i "s/80/${PORT}/g" /etc/apache2/ports.conf /etc/apache2/sites-available/000-default.conf
fi

cd /var/www/html

# First-boot setup. Every step is idempotent, so restarting a container never
# duplicates data. Set RUN_MIGRATIONS=false to skip when the schema is managed
# elsewhere.
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
  tries=0
  until php artisan migrate --force; do
    tries=$((tries + 1))
    if [ "$tries" -ge 20 ]; then
      echo "Database is not reachable after 20 attempts - giving up." >&2
      exit 1
    fi
    echo "Waiting for the database (attempt ${tries}/20)..."
    sleep 3
  done

  # Loads the validated question bank only when the bank is empty.
  php artisan content:import

  # Creates/updates the super admin from SUPER_ADMIN_EMAIL/SUPER_ADMIN_PASSWORD.
  if [ -n "$SUPER_ADMIN_PASSWORD" ]; then
    php artisan db:seed --class=SuperAdminSeeder --force
  fi
fi

# Question images are served from /storage/...; a stale link copied in from a
# developer machine would point at a path that doesn't exist here.
rm -f public/storage
php artisan storage:link

# Config/route caching needs real env vars, which only exist at container
# start (not at `docker build` time) on these hosts - so this runs here,
# not as a Dockerfile RUN step.
php artisan config:cache
php artisan route:cache

exec apache2-foreground
