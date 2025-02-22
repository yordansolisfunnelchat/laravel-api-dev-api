FROM php:8.2-cli

# Instalar dependencias del sistema
RUN apt-get update && apt-get install -y \
    zip \
    unzip \
    libzip-dev \
    libpq-dev \
    libicu-dev \
    libgd-dev \
    libexif-dev \
    && rm -rf /var/lib/apt/lists/*

# Instalar extensiones PHP requeridas
RUN docker-php-ext-configure intl \
    && docker-php-ext-install \
    pdo \
    pdo_mysql \
    zip \
    intl \
    exif \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd

# Permitir ejecutar Composer como superusuario
ENV COMPOSER_ALLOW_SUPERUSER=1

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configurar el directorio de trabajo
WORKDIR /var/task

# Copiar los archivos del proyecto
COPY . .

# Instalar dependencias de producción
RUN composer install --no-dev --optimize-autoloader

# Configurar permisos
RUN chmod -R 755 storage bootstrap/cache

# Exponer puerto para pruebas locales
EXPOSE 8080

# Comando para ejecutar en desarrollo local
CMD ["php", "lambda-handler.php"]