FROM php:8.4-cli-bookworm

WORKDIR /var/www/html

ENV DEBIAN_FRONTEND=noninteractive \
    TZ=UTC \
    APP_PORT=8000 \
    COMPOSER_ALLOW_SUPERUSER=1

RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    supervisor \
    postgresql-client \
    default-mysql-client \
    nano \
    && rm -rf /var/lib/apt/lists/*

ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions \
        pdo_pgsql \
        pdo_mysql \
        mbstring \
        xml \
        curl \
        zip \
        bcmath \
        intl \
        soap \
        redis \
        sockets \
        pcntl \
        gd \
        opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy composer files and install production dependencies
COPY composer.json composer.lock /var/www/html/
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --ignore-platform-req=php+

# Copy application files
COPY . /var/www/html

# Dump optimized autoloader
RUN composer dump-autoload --optimize --no-dev

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
COPY supervisord.docker.conf /etc/supervisor/conf.d/supervisord.conf

RUN chmod +x /usr/local/bin/docker-entrypoint.sh

RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache || true \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache || true

EXPOSE 8000

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
