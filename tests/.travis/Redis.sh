INI_PATH=`php -r "echo php_ini_loaded_file();"`

sudo kill -9 `sudo lsof -t -i:6379` # kill listeners on required port
docker run -d -p 6379:6379 redis

pecl install redis

echo 'extension="redis.so"' >> $INI_PATH
