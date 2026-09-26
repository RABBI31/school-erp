# Development image: PHP 8.4 + Node 20 + Chromium (for Bangla PDFs)
FROM php:8.4-cli-bookworm

ARG UID=1000
ARG GID=1000

RUN apt-get update && apt-get install -y --no-install-recommends \
      git unzip zip curl ca-certificates gnupg procps \
      libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libicu-dev libonig-dev \
      default-mysql-client \
      chromium \
      fonts-beng fonts-noto-core fonts-noto-color-emoji \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql mbstring zip gd bcmath intl exif pcntl \
    && rm -rf /var/lib/apt/lists/*

# Node 20 (for Vite and Browsershot)
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Browsershot uses puppeteer, but Chromium is already installed above
ENV PUPPETEER_SKIP_DOWNLOAD=true \
    PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium \
    NODE_PATH=/usr/lib/node_modules
RUN npm install -g puppeteer

COPY docker/php.ini /usr/local/etc/php/conf.d/99-app.ini

# Run as a normal user so files created in the container stay editable on your machine
RUN groupadd -g ${GID} app 2>/dev/null || true \
    && useradd -u ${UID} -g ${GID} -m -s /bin/bash app 2>/dev/null || true \
    && mkdir -p /var/www/html && chown -R ${UID}:${GID} /var/www/html

WORKDIR /var/www/html
USER ${UID}:${GID}

EXPOSE 8000 5173
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
