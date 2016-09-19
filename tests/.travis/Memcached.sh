PHP_VERSION=`php -r "echo phpversion();"`

sudo kill -9 `sudo lsof -t -i:11211` # kill listeners on required port
docker run -d -p 11211:11211 memcached

if [[ $PHP_VERSION != *"hhvm" ]]
then
    if [[ `php-config --vernum` -ge 70000 ]] # PHP>=7.0
    then
        sudo apt-get update
        sudo apt-get install -y libmemcached-dev libmemcached11 git build-essential

        git clone https://github.com/php-memcached-dev/php-memcached
        cd php-memcached
        git checkout php7
        git pull

        /usr/local/php7/bin/phpize
        ./configure --with-php-config=/usr/local/php7/bin/php-config

        make
        sudo make install
    else # PHP<7.0
        pecl uninstall memcached

        # install ext-memcached requirements:
        # "error: memcached support requires ZLIB"
        # "error: memcached support requires libmemcached"
        sudo apt-get -y install zlib1g-dev libmemcached-dev
        printf "no --disable-memcached-sasl\n" | pecl install memcached
    fi
fi
