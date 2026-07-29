FROM composer:2

RUN docker-php-ext-install pdo_mysql
RUN apk add --no-cache curl-dev
RUN docker-php-ext-install curl

# Lihat docker/opcache.ini: `artisan serve` jalan di SAPI CLI yang defaultnya
# tanpa opcache, jadi tiap request meng-compile ulang seluruh Laravel.
COPY docker/opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini

WORKDIR /var/www/html

CMD ["sh", "docker/entrypoint.sh"]
