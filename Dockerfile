# syntax=docker/dockerfile:1

# ---- Stage 1: build front-end assets (Vite + Tailwind) ----
# glibc-based (not alpine/musl) to avoid Rollup/Vite native-binary issues.
FROM node:20-slim AS assets
WORKDIR /app
# NOTE: the committed package-lock.json is generated on Windows and only pins the
# Windows native binaries. npm (bug #4828) then refuses to install the Linux
# Rolldown/Vite binary on either `npm ci` or lockfile-based `npm install`. So we
# deliberately DO NOT copy the lockfile here and let npm resolve fresh for Linux.
COPY package.json vite.config.js tailwind.config.js postcss.config.js ./
RUN npm install --no-audit --no-fund
COPY resources ./resources
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

# Restricted runtimes (e.g. Render) refuse to exec a binary that carries file
# capabilities. We bind to a high port ($PORT), so drop the caps from frankenphp
# to avoid "exec: frankenphp: Operation not permitted" (exit 126).
RUN setcap -r "$(command -v frankenphp)" 2>/dev/null || true

# Default command = web server. The Reverb service overrides this with:
#   php artisan reverb:start --host 0.0.0.0 --port $PORT
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
