# Shared Laravel app image for Bproo monorepo hosts.
# Build from repo root, e.g.:
#   docker build -f deployment/docker/Dockerfile.app --build-arg APP_DIR=apps/erp --target app -t bproo/erp:latest .
#
# Composer path repos in apps/*/composer.json resolve to ../../packages → /packages in the image.

ARG APP_DIR=apps/erp

FROM composer:2 AS vendor
ARG APP_DIR
WORKDIR /app
COPY ${APP_DIR}/composer.json ${APP_DIR}/composer.lock ./
# Path repos: ../../packages/... from /app → /packages/...
COPY packages /packages
RUN composer install --no-dev --prefer-dist --no-interaction --no-scripts --no-autoloader

FROM node:20-alpine AS frontend
ARG APP_DIR
WORKDIR /app
COPY ${APP_DIR}/package*.json ./
RUN npm ci
COPY ${APP_DIR}/ .
RUN npm run build

FROM php:8.2-fpm-alpine AS app
ARG APP_DIR

RUN apk add --no-cache \
    bash \
    fcgi \
    icu-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    postgresql-dev \
    oniguruma-dev \
    zip \
    unzip \
    git \
    shadow \
    $PHPIZE_DEPS

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo pdo_pgsql mbstring intl zip gd bcmath \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS

WORKDIR /var/www/html

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --from=vendor /app/vendor ./vendor
COPY --from=vendor /packages /packages
COPY ${APP_DIR}/ .
COPY --from=frontend /app/public/build ./public/build

# Dummy key only for image build (real APP_KEY comes from .env.production at runtime)
RUN APP_ENV=production APP_KEY=base64:ZHVtbXlCdWlsZEtleUZvckRvY2tlckltYWdlMTIzNDU= \
    composer dump-autoload --optimize --no-interaction \
    && php artisan package:discover --ansi

RUN mkdir -p database/migrations/tenant_modules \
    && chown -R www-data:www-data storage bootstrap/cache database/migrations/tenant_modules \
    && chmod -R ug+rwx storage bootstrap/cache database/migrations/tenant_modules

COPY ${APP_DIR}/deploy/docker/php-entrypoint.sh /usr/local/bin/php-entrypoint.sh
RUN chmod +x /usr/local/bin/php-entrypoint.sh

ENTRYPOINT ["php-entrypoint.sh"]
CMD ["php-fpm"]

# Nginx — static files from app stage
FROM nginx:1.27-alpine AS web
ARG APP_DIR
COPY --from=app /var/www/html/public /var/www/html/public
COPY ${APP_DIR}/deploy/docker/nginx/default.conf /etc/nginx/conf.d/default.conf
