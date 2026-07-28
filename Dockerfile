# syntax=docker/dockerfile:1

# -----------------------------------------------------------------
# Basis-Stage: gemeinsame Grundlage für dev und prod
# -----------------------------------------------------------------
FROM dunglas/frankenphp:1-php8.5-bookworm AS base

WORKDIR /app

# System-Pakete, die für PHP-Extensions / Composer benötigt werden
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    libicu-dev \
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    && rm -rf /var/lib/apt/lists/*

# PHP-Extensions installieren (Helper-Skript ist im Basis-Image enthalten)
RUN install-php-extensions \
    pdo_mysql \
    mysqli \
    redis \
    intl \
    zip \
    gd \
    opcache \
    pcntl \
    bcmath

# Composer aus offiziellem Image kopieren statt separat zu installieren
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# -----------------------------------------------------------------
# Development-Stage
# -----------------------------------------------------------------
FROM base AS development

# Xdebug für PHPStorm-Debugging (nur in dev installieren!)
RUN install-php-extensions xdebug

COPY docker/php/php.dev.ini /usr/local/etc/php/conf.d/99-app.ini
COPY docker/caddy/Caddyfile /etc/frankenphp/Caddyfile

# Non-root User mit gleicher UID/GID wie der Host-User (macOS Standard: 501/20 oder 1000/1000)
ARG USER_UID=1000
ARG USER_GID=1000
RUN groupadd -g ${USER_GID} appuser 2>/dev/null || true \
    && useradd -u ${USER_UID} -g ${USER_GID} -m appuser 2>/dev/null || true

# Im Dev-Container wird der Code per Bind-Mount eingehängt (siehe docker-compose.yml),
# daher hier kein COPY des Anwendungscodes.

EXPOSE 80 443 443/udp

CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]

# -----------------------------------------------------------------
# Production-Stage
# -----------------------------------------------------------------
FROM base AS production

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php/php.prod.ini /usr/local/etc/php/conf.d/99-app.ini
COPY docker/caddy/Caddyfile /etc/frankenphp/Caddyfile

# Composer-Dependencies separat installieren (besseres Layer-Caching)
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist

COPY . .
RUN composer dump-autoload --optimize \
    && chown -R www-data:www-data /app/storage /app/bootstrap/cache

USER www-data

EXPOSE 80 443 443/udp

CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
