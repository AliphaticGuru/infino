# ======================================
# Stage 1: Builder (for compiling PHP extensions)
# ======================================
FROM php:8.4.8-fpm-alpine AS builder

RUN set -eux; \
    sed -i 's|https://dl-cdn.alpinelinux.org|https://mirrors.aliyun.com|g' /etc/apk/repositories; \
    apk update && \
    apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        libzip-dev libpng-dev libjpeg-turbo-dev libwebp-dev freetype-dev \
        icu-dev libxml2-dev oniguruma-dev postgresql-dev && \
    docker-php-ext-configure zip && \
    docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp && \
    docker-php-ext-install -j"$(nproc)" pdo_pgsql mbstring zip intl gd && \
    apk del .build-deps && \
    rm -rf /var/cache/apk/* /tmp/*

# ======================================
# Stage 2: Runtime Base Image
# ======================================
FROM php:8.4.8-fpm-alpine AS base-laravel

# Switch repository mirror for speed
RUN set -eux; \
    sed -i 's|https://dl-cdn.alpinelinux.org|https://mirrors.aliyun.com|g' /etc/apk/repositories; \
    apk update && \
    apk add --no-cache \
        bash curl git zip unzip \
        libpq libzip libpng libjpeg-turbo libwebp freetype \
        icu libxml2 oniguruma postgresql-libs && \
    rm -rf /var/cache/apk/* /tmp/*

# Copy compiled PHP extensions from builder
COPY --from=builder /usr/local/lib/php/extensions /usr/local/lib/php/extensions
COPY --from=builder /usr/local/etc/php/conf.d /usr/local/etc/php/conf.d

# Copy PHP configuration
COPY docker/php-fpm/99-custom.ini /usr/local/etc/php/conf.d/
COPY docker/php-fpm/zz-dynamic.conf /usr/local/etc/php-fpm.d/

# Copy Composer binary (only 4MB)
COPY --from=composer:2.8.8 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Permissions and user setup
RUN chown -R www-data:www-data /var/www/html
USER www-data

EXPOSE 9000
CMD ["php-fpm"]/