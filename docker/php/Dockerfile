FROM php:8.3-fpm

# 1. Instalar dependencias del sistema
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libpq-dev \
    libzip-dev

# 2. Limpiar caché de apt para reducir el tamaño de la imagen
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# 3. Instalar la extensión de Redis mediante PECL y habilitarla
RUN pecl install redis && docker-php-ext-enable redis

# 4. Instalar extensiones de PHP necesarias para Laravel y PostgreSQL
RUN docker-php-ext-install pdo pdo_pgsql pgsql mbstring exif pcntl bcmath gd zip

# 5. Copiar el ejecutable de Composer desde la imagen oficial
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 6. Establecer el directorio de trabajo
WORKDIR /var/www

# 7. Crear subdirectorios requeridos de storage y bootstrap/cache con permisos correctos
RUN mkdir -p /var/www/storage/framework/views \
    /var/www/storage/framework/sessions \
    /var/www/storage/framework/cache/data \
    /var/www/storage/logs \
    /var/www/bootstrap/cache \
    && chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache \
    && chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# 8. Cambiar al usuario no administrativo 'www-data' para la ejecución del contenedor
USER www-data

CMD ["php-fpm"]