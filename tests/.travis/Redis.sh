INI_PATH=`php -r "echo php_ini_loaded_file();"`

docker run -d -p 6378:6379 redis

printf "\n" | pecl install redis

echo 'extension="redis.so"' >> $INI_PATH
