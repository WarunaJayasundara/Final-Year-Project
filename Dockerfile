# Single-service deploy: builds the React/Vite frontend, then serves it as
# static files from the Laravel backend's public/ directory alongside the
# /api/* routes - one origin, one free host, no cross-site cookie issues
# (Sanctum's SPA session auth is far simpler same-origin than split across
# two different hosting domains).

# ---- Stage 1: build the frontend ----
FROM node:20-alpine AS frontend-build
WORKDIR /app/frontend
COPY frontend/package*.json ./
RUN npm ci
COPY frontend/ ./
RUN npm run build

# ---- Stage 2: PHP backend, serving the built frontend ----
FROM php:8.0-apache

RUN apt-get update && apt-get install -y \
        libzip-dev zip unzip git libpng-dev libonig-dev \
    && docker-php-ext-install pdo_mysql mbstring zip gd bcmath \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY backend/ ./

RUN composer install --no-dev --optimize-autoloader --no-interaction

# Built SPA goes into Laravel's public/ dir - the fallback route in
# routes/web.php serves its index.html for every non-API GET request.
COPY --from=frontend-build /app/frontend/dist/ ./public/

RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80
ENTRYPOINT ["/entrypoint.sh"]
