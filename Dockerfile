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

# ============================================================
# Browser testing (Pest 4 + Playwright) en Alpine
# Playwright NO soporta sus navegadores (glibc) en Alpine (musl). Solución:
# Chromium del sistema + reemplazar los binarios que Playwright espera por
# wrappers que ejecutan /usr/bin/chromium. El cache se hornea en /root/.cache
# (filesystem de la IMAGEN, fuera del bind-mount) para sobrevivir a recrear el
# contenedor. PLAYWRIGHT_VERSION DEBE coincidir con "playwright" en package.json.
# ============================================================
ARG PLAYWRIGHT_VERSION=1.61.1
RUN apk add --no-cache chromium nss freetype harfbuzz ttf-freefont \
    && npx --yes playwright@${PLAYWRIGHT_VERSION} install chromium \
    && find /root/.cache/ms-playwright -type f \( -name chrome -o -name chrome-headless-shell \) | while read -r b; do \
         rm -f "$b"; \
         printf '#!/bin/sh\nexec /usr/bin/chromium --headless=new --no-sandbox --disable-gpu --disable-dev-shm-usage "$@"\n' > "$b"; \
         chmod +x "$b"; \
       done
# Evita que `npm ci`/`install` (runtime) re-descargue navegadores glibc inútiles:
# los browsers ya están horneados arriba.
ENV PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD=1

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
