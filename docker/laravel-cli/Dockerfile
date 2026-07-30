# ======================================
# Stage 1: PHP Extension Builder
# ======================================
FROM php:8.5.7-cli-alpine3.23 AS builder

RUN apk add --no-cache \
    $PHPIZE_DEPS \
    libzip-dev \
    postgresql-dev \
    icu-dev \
    oniguruma-dev


RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    mbstring \
    intl \
    zip

# ======================================
# Stage 2: Laravel Development CLI
# ======================================
FROM php:8.5.7-cli-alpine3.23

WORKDIR /var/www/html

RUN apk add --no-cache \
    bash \
    curl \
    git \
    unzip \
    zip \
    libzip \
    postgresql-libs \
    icu \
    nodejs \
    npm \
    oniguruma

# Copy compiled PHP extensions
COPY --from=builder /usr/local/lib/php/extensions /usr/local/lib/php/extensions
COPY --from=builder /usr/local/etc/php/conf.d /usr/local/etc/php/conf.d


# Composer
COPY --from=composer:2.8.8 /usr/bin/composer /usr/bin/composer

# Laravel Installer
ENV PATH="/root/.composer/vendor/bin:$PATH"

RUN composer global require laravel/installer

# WORKDIR /var/www/html

CMD ["bash"]










# FROM node:lts-alpine3.23


# WORKDIR /var/www/html


# # Install system utilities
# RUN apk add --no-cache \
#     bash \
#     git \
#     curl \
#     unzip


# # Install Composer
# COPY --from=composer:2.8.8 /usr/bin/composer /usr/bin/composer


# # Install Laravel installer
# RUN composer global require laravel/installer


# ENV PATH="/root/.composer/vendor/bin:$PATH"


# EXPOSE 5173


# CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0"]