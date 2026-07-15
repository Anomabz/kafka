FROM composer:2

RUN docker-php-ext-install pdo_mysql
RUN apk add --no-cache curl-dev
RUN docker-php-ext-install curl
WORKDIR /var/www/html

CMD ["sh", "docker/entrypoint.sh"]
