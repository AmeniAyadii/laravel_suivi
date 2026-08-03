# Laravel Dockerfile pour Render
FROM php:8.4-cli

WORKDIR /app

# Installer les dépendances système
RUN apt-get update && apt-get install -y \
    git \
    curl \
    zip \
    unzip \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Installer Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copier tout le code
COPY . .

# 🔥 CRÉER LE FICHIER .env AVANT LA GÉNÉRATION DE LA CLÉ
RUN if [ ! -f .env ]; then cp .env.example .env || echo "APP_ENV=production" > .env; fi

# Installer les dépendances
RUN composer install --no-dev --optimize-autoloader

# Générer la clé
RUN php artisan key:generate

# Permissions
RUN chmod -R 777 storage bootstrap/cache

# Variables d'environnement
ENV APP_ENV=production
ENV APP_DEBUG=false

EXPOSE 10000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=10000"]