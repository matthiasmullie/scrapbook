INI_PATH=`php -r "echo php_ini_loaded_file();"`

sudo kill -9 `sudo lsof -t -i:6379` # kill listeners on required port
docker run -d -p 6379:6379 redis

pecl uninstall redis

if [[ `php-config --vernum` -ge 70000 ]] # PHP>=7.0
then
    pecl install redis
else
    pecl install redis-2.2.7
fi

echo 'extension="redis.so"' >> $INI_PATH
