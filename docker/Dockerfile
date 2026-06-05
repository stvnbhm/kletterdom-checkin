FROM php:8.4-fpm

RUN apt-get update && apt-get install -y \
    git \
    curl \
    zip \
    unzip \
    default-mysql-client \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libicu-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mysqli \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Beim ersten Build ohne Volume: Composer-Deps installieren.
# Mit Host-Volume-Mount überschreibt ./vendor den Image-Inhalt.
COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-scripts --no-interaction --optimize-autoloader 2>/dev/null \
    || composer install --no-dev --no-scripts --no-interaction --optimize-autoloader

RUN mkdir -p storage/sessions storage/throttle backups \
    && chown -R www-data:www-data storage backups \
    && chmod -R 775 storage backups

EXPOSE 9000

CMD ["php-fpm"]
