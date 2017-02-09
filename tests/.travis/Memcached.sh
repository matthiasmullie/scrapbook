PHP_VERSION=`php -r "echo phpversion();"`

sudo kill -9 `sudo lsof -t -i:11211` # kill listeners on required port
docker run -d -p 11211:11211 memcached

if [[ $PHP_VERSION != *"hhvm" ]]
then
    pecl uninstall memcached

    # install ext-memcached requirements:
    # "error: memcached support requires ZLIB"
    # "error: memcached support requires libmemcached"
    sudo apt-get -y install zlib1g-dev libmemcached-dev

    if [[ `php-config --vernum` -ge 70000 ]] # PHP>=7.0
    then
        printf "no --disable-memcached-sasl\n" | pecl install memcached
    else # PHP<7.0
        printf "no --disable-memcached-sasl\n" | pecl install memcached-2.2.0
    fi
fi
