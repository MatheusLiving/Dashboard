FROM php:8.2-apache

# Extensões exigidas pela aplicação: PDO/MySQL, zip e gd (PhpWord), intl e mbstring.
RUN apt-get update && apt-get install -y --no-install-recommends \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libicu-dev \
        libonig-dev \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql zip gd intl mbstring opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# URL limpas: a reescrita é feita pelo .htaccess de /public.
RUN a2enmod rewrite headers

# A raiz pública é /public — nenhum outro diretório é servido pelo Apache.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Permite que o .htaccess do projeto tenha efeito.
RUN printf '<Directory /var/www/html/public>\n    AllowOverride All\n    Require all granted\n</Directory>\n' \
    > /etc/apache2/conf-available/z-projeto.conf \
    && a2enconf z-projeto

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
