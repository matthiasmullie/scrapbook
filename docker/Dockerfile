FROM php:cli

RUN apt-get update

WORKDIR /var/www
COPY composer.json composer.json
COPY docker docker

RUN docker/build-php.sh

ENTRYPOINT ["/bin/sh", "-c"]
CMD ["vendor/bin/phpunit"]
