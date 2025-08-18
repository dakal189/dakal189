FROM php:8.3-cli

RUN apt-get update && apt-get install -y git unzip libpng-dev libjpeg-dev libfreetype6-dev \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install pdo_mysql gd \
 && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# Install Composer
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
 && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
 && php -r "unlink('composer-setup.php');"

CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]

