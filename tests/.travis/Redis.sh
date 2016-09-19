PHP_VERSION=`php -r "echo phpversion();"`

sudo kill -9 `sudo lsof -t -i:6379` # kill listeners on required port
docker run -d -p 6379:6379 redis

if [[ $PHP_VERSION != *"hhvm" ]]
then
    pecl uninstall redis

    if [[ `php-config --vernum` -ge 70000 ]] # PHP>=7.0
    then
        pecl install redis
    else
        pecl install redis-2.2.7
    fi
fi
