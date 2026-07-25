# syntax=docker/dockerfile:1

# ---- Stage 1: build front-end assets (Vite + Tailwind, incl. Echo) ----
FROM node:20-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json vite.config.js tailwind.config.js postcss.config.js ./
RUN npm ci
COPY resources ./resources
# VITE_REVERB_* are baked in at build time; pass them as build args in production.
ARG VITE_REVERB_APP_KEY
ARG VITE_REVERB_HOST
ARG VITE_REVERB_PORT
ARG VITE_REVERB_SCHEME
RUN npm run build

# ---- Stage 2: PHP runtime (FrankenPHP, production server) ----
FROM dunglas/frankenphp:1-php8.4

# PHP extensions the app needs (Postgres, money/bcmath, etc.).
RUN install-php-extensions pdo_pgsql pgsql intl zip gd bcmath opcache pcntl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Install PHP deps first (better layer caching).
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

# App source + built assets.
COPY . .
COPY --from=assets /app/public/build ./public/build
RUN composer dump-autoload --optimize --no-dev \
    && chown -R www-data:www-data storage bootstrap/cache

# Server config.
COPY docker/Caddyfile /etc/caddy/Caddyfile
ENV PORT=8080
EXPOSE 8080

# Default command = web server. The Reverb service overrides this with:
#   php artisan reverb:start --host 0.0.0.0 --port $PORT
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
