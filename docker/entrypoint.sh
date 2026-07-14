#!/bin/sh
set -e

# Most free PaaS hosts (Render, Railway, Fly.io) inject the port to bind via
# $PORT rather than always using 80 - remap Apache's listen port at container
# start (not build time), since $PORT isn't known until the container runs.
if [ -n "$PORT" ] && [ "$PORT" != "80" ]; then
  sed -i "s/80/${PORT}/g" /etc/apache2/ports.conf /etc/apache2/sites-available/000-default.conf
fi

# Config/route caching needs real env vars, which only exist at container
# start (not at `docker build` time) on these hosts - so this runs here,
# not as a Dockerfile RUN step.
php artisan config:cache
php artisan route:cache

exec apache2-foreground
