#!/bin/bash

apt-get update

apt-get install --reinstall -y ca-certificates
apt-get install -y libssl-dev
apt-get install -y zlib1g-dev libzip-dev
apt-get install -y libmemcached-dev
apt-get install -y libpq-dev

pecl install -f xdebug
pecl install -f igbinary

if [[ `php-config --vernum` -ge 70400 ]]; then # PHP>=7.4
  if [[ `php-config --vernum` -lt 80000 ]]; then # PHP<8
    pecl install -f json
  fi
  apt-get install -y cmake
  pecl install -f couchbase
fi

if [[ `php-config --vernum` -ge 70000 ]]; then # PHP>=7.0
    pecl install -f apcu
    pecl install -f memcached
    pecl install -f redis
else # PHP<7.0
    pecl install -f apcu-4.0.10
    pecl install -f memcached-2.2.0
    pecl install -f redis-2.2.7
fi

docker-php-ext-enable apcu
echo "apc.enable_cli=1" >> /usr/local/etc/php/php.ini
docker-php-ext-enable xdebug
docker-php-ext-enable igbinary
docker-php-ext-enable couchbase
docker-php-ext-enable memcached
docker-php-ext-enable redis
docker-php-ext-install pdo
docker-php-ext-install pdo_mysql
docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql
docker-php-ext-install pdo_pgsql
docker-php-ext-install pcntl

# cache dir for flysystem
mkdir /tmp/cache

rm -rf /tmp/pear

# composer requirements
apt-get install -y wget git zip unzip
docker-php-ext-install zip

# install dependencies
make install || true
