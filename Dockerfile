ARG version=cli
FROM php:$version

WORKDIR /var/www
COPY . .

RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

RUN docker/php.sh
