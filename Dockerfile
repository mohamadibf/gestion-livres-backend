# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# Étape 1 : compilation du frontend React (dépôt public gestion-livres-frontend).
# L'application est servie par Laravel : même origine que l'API, donc cookies Sanctum
# et CSRF sans configuration CORS.
# ---------------------------------------------------------------------------
FROM node:22-alpine AS frontend
RUN apk add --no-cache git

ARG FRONTEND_REPO=https://github.com/mohamadibf/gestion-livres-frontend.git
ARG FRONTEND_REF=feature/fonctions-stitch

RUN git clone --depth 1 --branch "${FRONTEND_REF}" "${FRONTEND_REPO}" /app
WORKDIR /app
RUN npm ci

# Variable vide = URLs relatives (/api, /storage) sur la même origine
ENV VITE_API_URL=""
RUN npm run build

# ---------------------------------------------------------------------------
# Étape 2 : Laravel (PHP 8.3 + Nginx + php-fpm)
# ---------------------------------------------------------------------------
FROM serversideup/php:8.3-fpm-nginx AS app

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    DB_CONNECTION=pgsql \
    SESSION_DRIVER=database \
    CACHE_STORE=database \
    QUEUE_CONNECTION=sync \
    PHP_OPCACHE_ENABLE=1 \
    # Migrations et mise en cache au démarrage (serversideup). Le cache des routes est désactivé :
    # certaines routes sont des closures, non sérialisables.
    AUTORUN_ENABLED=true \
    AUTORUN_LARAVEL_ROUTE_CACHE=false

WORKDIR /var/www/html

# Dépendances PHP d'abord (couche mise en cache tant que composer.lock ne change pas)
COPY --chown=www-data:www-data composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY --chown=www-data:www-data . .
RUN composer dump-autoload --optimize --no-dev --no-interaction \
    && mkdir -p storage/app/public/books storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache

# Frontend compilé : index.html et assets/ à la racine publique, à côté de index.php
COPY --from=frontend --chown=www-data:www-data /app/dist/ ./public/

# Création du premier administrateur au démarrage (après les migrations de serversideup)
COPY --chmod=755 docker/60-ensure-admin.sh /etc/entrypoint.d/60-ensure-admin.sh

# Render envoie le trafic sur le port défini par la variable PORT (à régler sur 8080)
EXPOSE 8080
