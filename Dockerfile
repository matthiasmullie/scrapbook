ARG version
FROM php:$version

WORKDIR /var/www
COPY makefile makefile
COPY composer.json composer.json
COPY docker docker

RUN docker/php.sh
