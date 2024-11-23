#!/bin/bash

apt-get update
apt-get install --reinstall -y ca-certificates
apt-get install -y libssl-dev zlib1g-dev libzip-dev libmemcached-dev libpq-dev git wget

# build cmake; the apt-get version is too old for couchbase
wget https://github.com/Kitware/CMake/releases/download/v3.29.3/cmake-3.29.3.tar.gz -P /tmp
tar -xf /tmp/cmake-3.29.3.tar.gz -C /tmp
cd /tmp/cmake-3.29.3
./configure
make
make install
cd /var/www # back to work dir

pecl install -f couchbase
pecl install -f xdebug
pecl install -f igbinary
pecl install -f apcu
pecl install -f memcached
pecl install -f redis

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
