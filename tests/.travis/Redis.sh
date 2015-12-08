INI_PATH=`php -r "echo php_ini_loaded_file();"`
echo 'extension="redis.so"' >> $INI_PATH
