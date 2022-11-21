FROM php:8.1-fpm

LABEL Maintainer="Ovanes Tsatsuryan <ovikus@gmail.com>" \
      Description="Base docker setup for local/dev environment"

ARG PUID=1000
ENV PUID ${PUID}
ARG PGID=1000
ENV PGID ${PGID}

RUN apt-get update && apt-get install -y \
    build-essential \
    libicu-dev \
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    locales \
    zip \
    unzip \
    jpegoptim optipng pngquant gifsicle \
    vim \
    git \
    curl \
    wget \
    libpq-dev

RUN docker-php-ext-configure zip

RUN docker-php-ext-install \
        mbstring \
        zip \
        pdo \
        pgsql \
        pdo_pgsql \
        opcache

RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Copy existing app directory
COPY ./ /var/www
WORKDIR /var/www

RUN groupmod -o -g ${PGID} www-data && \
    usermod -o -u ${PUID} -g www-data www-data

RUN chown -R www-data:www-data /var/www

# Copy and run composer
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

USER www-data
RUN composer install --no-interaction

# For Laravel Installations
#RUN php artisan key:generate

EXPOSE 9000

CMD ["php-fpm"]
