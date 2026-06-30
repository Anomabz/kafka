FROM composer:2

RUN docker-php-ext-install pdo_mysql

WORKDIR /var/www/html

CMD ["sh", "docker/entrypoint.sh"]
