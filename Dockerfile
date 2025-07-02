# Dockerfile

# --- Stage 1: Base PHP Apache pour le développement et la construction des assets ---
# Utilise php:8.2-apache qui inclut déjà la plupart des extensions nécessaires.
FROM php:8.2-apache AS base_dev_builder

ENV DEBIAN_FRONTEND=noninteractive

# Installer les dépendances système nécessaires pour les extensions PHP et Node.js.
# - libzip-dev: pour l'extension zip (si non incluse par défaut, mais souvent l'est).
# - libicu-dev: pour l'extension intl (si non incluse).
# - libpng-dev, libjpeg-dev, libfreetype6-dev: pour l'extension gd (si non incluse ou pour reconfigurer).
# - libonig-dev: pour l'extension mbstring (si non incluse).
# - libxml2-dev: pour des extensions comme SimpleXML, DOM, etc.
# - libsqlite3-dev: pour pdo_sqlite (nécessaire pour la compilation de pdo_sqlite si non incluse).
# - nodejs, npm: pour la compilation des assets frontend.
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    libzip-dev \
    libicu-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libsqlite3-dev \
    nodejs \
    npm \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Installer et activer UNIQUEMENT les extensions PHP qui ne sont PAS incluses par défaut.
# D'après les logs, la plupart sont déjà chargées. Seul pdo_mysql reste à installer explicitement.
RUN docker-php-ext-install -j$(nproc) pdo_mysql

# Copier Composer (version stable recommandée)
COPY --from=composer:2.7.7 /usr/bin/composer /usr/bin/composer

# Définir le répertoire de travail
WORKDIR /var/www/html

# --- Stage 2: Dépendances PHP et Node.js (pour les assets) ---
FROM base_dev_builder AS dependencies_builder

COPY composer.json composer.lock ./
COPY package.json package-lock.json ./

RUN composer install --no-interaction --no-scripts --prefer-dist --optimize-autoloader

RUN npm install

# --- Stage 3: Construction des assets frontend ---
FROM dependencies_builder AS frontend_builder

COPY resources/ ./resources/
COPY postcss.config.js tailwind.config.js vite.config.js ./

RUN npm run build

# --- Stage 4: Environnement de développement (avec Xdebug) ---
FROM base_dev_builder AS dev

COPY . /var/www/html

RUN pecl install xdebug && docker-php-ext-enable xdebug

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-custom.ini
COPY docker/apache/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
RUN a2enmod rewrite

RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache \
    && mkdir -p /var/lib/php/sessions && chown -R www-data:www-data /var/lib/php/sessions

EXPOSE 80
CMD ["apache2-foreground"]

# --- Stage 5: Environnement de production (image finale légère) ---
# Utilise php:8.2-apache-alpine qui est plus légère et inclut aussi des extensions par défaut.
FROM php:8.2-apache-alpine AS final

# Installer les dépendances système nécessaires pour PHP sur Alpine.
# - libzip-dev, libicu-dev, libpng-dev, libjpeg-turbo-dev, libwebp-dev, libfreetype-dev, libonig-dev, libxml2-dev, sqlite-dev:
#   Ces libs sont les dépendances pour les extensions PHP qui sont souvent incluses par défaut dans les images Alpine-Apache.
#   On les garde ici pour s'assurer que les extensions pré-compilées ont leurs libs nécessaires.
RUN apk add --no-cache \
    libzip-dev \
    libicu-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    libfreetype-dev \
    libonig-dev \
    libxml2-dev \
    sqlite-dev \
    && rm -rf /var/cache/apk/*

# Installer et activer UNIQUEMENT les extensions PHP qui ne sont PAS incluses par défaut sur Alpine.
# D'après les images Alpine-Apache, la plupart sont déjà activées. Seul pdo_mysql reste à installer explicitement.
RUN docker-php-ext-install -j$(nproc) pdo_mysql

WORKDIR /var/www/html

COPY --from=dependencies_builder /var/www/html/vendor /var/www/html/vendor

COPY . /var/www/html

COPY --from=frontend_builder /var/www/html/public/build /var/www/html/public/build

COPY php.ini /usr/local/etc/php/conf.d/99-custom.ini
COPY docker/apache/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
RUN a2enmod rewrite

RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache \
    && mkdir -p /var/lib/php/sessions && chown -R www-data:www-data /var/lib/php/sessions

RUN php artisan storage:link || true
RUN php artisan optimize:clear \
    && php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache

USER www-data

EXPOSE 80
CMD ["apache2-foreground"]
