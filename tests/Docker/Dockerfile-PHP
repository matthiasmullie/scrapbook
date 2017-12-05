ARG version
FROM php:$version

WORKDIR /var/www
COPY makefile makefile
COPY composer.json composer.json
COPY tests/Docker tests/docker

RUN tests/docker/build-php.sh
