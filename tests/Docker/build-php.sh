#!/bin/bash

apt-get update
apt-get install -y gnupg
curl -sS https://packages.couchbase.com/clients/c/repos/deb/couchbase.key | apt-key add -
echo "deb https://packages.couchbase.com/clients/c/repos/deb/debian10 buster buster/main" > /etc/apt/sources.list.d/couchbase.list
apt-get update

curl -O -J "http://ftp.se.debian.org/debian/pool/main/o/openssl/libssl1.0.0_1.0.2l-1~bpo8+1_amd64.deb"
dpkg -i libssl1.0.0_1.0.2l-1~bpo8+1_amd64.deb

apt-get install -y libcouchbase3 libcouchbase-dev libcouchbase3-tools
apt-get install -y libmemcached-dev
apt-get install -y zlib1g-dev libzip-dev
apt-get install -y libpq-dev

pecl install -f pcs-1.3.3
pecl install -f igbinary
pecl install -f couchbase

if [[ `php-config --vernum` -ge 72000 ]]; then # PHP>=7.2
    pecl install -f xdebug
elif [[ `php-config --vernum` -ge 70000 ]]; then # PHP>=7.0
    pecl install -f xdebug-2.7.2
else # PHP<7.0
    pecl install -f xdebug-2.5.5
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
docker-php-ext-enable couchbase-2.6.2
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
make install
