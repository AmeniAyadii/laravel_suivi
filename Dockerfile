# Laravel Dockerfile pour Render
FROM php:8.4-cli

WORKDIR /app

# Installer les dépendances système
RUN apt-get update && apt-get install -y \
    git curl zip unzip \
    libpng-dev libonig-dev libxml2-dev libzip-dev \
    libfreetype6-dev libjpeg62-turbo-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Installer Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Créer .env valide
RUN echo "APP_NAME=MediTrack" > .env && \
    echo "APP_ENV=production" >> .env && \
    echo "APP_DEBUG=false" >> .env && \
    echo "APP_URL=https://laravel-suivi.onrender.com" >> .env && \
    echo "APP_KEY=" >> .env

# Copier le code
COPY . .

# Installer les dépendances (sans scripts)
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Générer la clé
#RUN php artisan key:generate
RUN php artisan key:generate --force || true

# Dump autoload
RUN composer dump-autoload --optimize

# Permissions
RUN chmod -R 777 storage bootstrap/cache

EXPOSE 10000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=10000"]