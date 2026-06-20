# AtendIa - Laravel 13 con PHP 8.5
FROM php:8.5-fpm-alpine

# Instalador oficial de extensiones (mlocati): resuelve compatibilidad con PHP 8.5,
# instala las libs necesarias y limpia las dependencias de build automaticamente.
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

# Extensiones requeridas por Laravel + Postgres + Redis
RUN install-php-extensions \
        pdo_pgsql \
        pgsql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
        opcache \
        sockets \
        redis

# Herramientas de runtime: nginx, supervisor, node (Vite) y utilidades
RUN apk add --no-cache \
        nginx \
        supervisor \
        nodejs \
        npm \
        git \
        curl \
        zip \
        unzip

# Composer 2
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configuraciones
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-custom.ini
COPY docker/php/php-fpm.conf /usr/local/etc/php-fpm.d/zz-custom.conf
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx/conf.d/laravel.conf /etc/nginx/http.d/default.conf
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Directorios de logs/runtime
RUN mkdir -p /var/log/supervisor /var/log/php /var/log/nginx /run/nginx

WORKDIR /var/www/html

EXPOSE 80 9000

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
