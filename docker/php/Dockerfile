FROM php:8.3-fpm

# Instalar dependencias del sistema
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

# Limpiar cache de apt
RUN apt-get clean && rm -rf /var/lib/apt/lists/*
# Instalar extensiones de PHP necesarias para PostgreSQL y Redis
RUN docker-php-ext-install pdo pdo_pgsql pgsql mbstring exif pcntl bcmath gd zip
# es para instalar el Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Establecer directorio de trabajo
WORKDIR /var/www
# es para que el usuario que se esté ejecutando en el contenedor sea el usuario que se esté ejecutando en el sistema operativo
USER www-data
CMD ["php-fpm"]