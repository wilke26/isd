# syntax=docker/dockerfile:1

# -----------------------------------------------------------------
# Basis-Stage: gemeinsame Grundlage für dev und prod
# -----------------------------------------------------------------
FROM dunglas/frankenphp:1-php8.5-bookworm AS base

WORKDIR /app

# System-Pakete, die für PHP-Extensions / Composer benötigt werden
# libcap2-bin liefert setcap (siehe development-Stage weiter unten)
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    libicu-dev \
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libcap2-bin \
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

# Non-root User mit gleicher UID/GID wie der Host-User (macOS Standard: 501/20).
#
# WICHTIG: Auf macOS ist die Host-GID meist 20 (Gruppe "staff"). Im Debian-
# Basisimage ist GID 20 bereits von der Systemgruppe "dialout" belegt.
# "groupadd -g 20 appuser" schlägt deshalb still fehl (durch "|| true"
# verschluckt) — appuser landet dadurch faktisch in der Gruppe "dialout",
# nicht in einer Gruppe namens "appuser". Das ist funktional unproblematisch
# (die GID stimmt, nur der Gruppenname nicht), führt aber bei manuellen
# `chown appuser:appuser`-Aufrufen zu "invalid group". Bei manuellen
# Eingriffen im Container daher die numerische GID verwenden, z.B.:
#   docker compose exec -u root app chown -R ${USER_UID}:${USER_GID} <pfad>
ARG USER_UID=1000
ARG USER_GID=1000
RUN groupadd -g ${USER_GID} appuser 2>/dev/null || true \
    && useradd -u ${USER_UID} -g ${USER_GID} -m appuser 2>/dev/null || true

# FrankenPHP/Caddy muss weiterhin an die privilegierten Ports 80/443 binden
# können, obwohl der Container künftig nicht mehr als root läuft. Statt
# dafür root zu bleiben, bekommt gezielt nur das frankenphp-Binary die dafür
# nötige Linux-Capability — der Rest des Containers läuft ohne Root-Rechte.
RUN setcap cap_net_bind_service=+ep "$(readlink -f "$(which frankenphp)")"

USER appuser

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
