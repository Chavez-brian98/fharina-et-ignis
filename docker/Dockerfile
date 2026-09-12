FROM php:8.2-apache

# 1. Instalar herramientas básicas del sistema (git y unzip son necesarios para Composer)
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    curl \
    && docker-php-ext-install pdo pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

# 2. Copiar Composer directamente desde la imagen oficial
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# 3. Instalar Node.js (v20 LTS) y npm
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

# 4. Configuración de Apache
RUN a2enmod rewrite
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf