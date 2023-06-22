ARG version=cli
FROM php:$version

WORKDIR /var/www
COPY . .

RUN docker/php.sh
