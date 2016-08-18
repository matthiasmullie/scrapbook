INI_PATH=`php -r "echo php_ini_loaded_file();"`
PHP_VERSION=`php -r "echo phpversion();"`

pecl uninstall apcu

if [[ $PHP_VERSION != *"hhvm" ]]
then
    if [[ `php-config --vernum` -ge 70000 ]] # PHP>=7.0
    then
        pecl config-set preferred_state beta
        printf "yes\n" | pecl install apcu
    else # PHP<7.0
        printf "yes\n" | pecl install apcu-4.0.10
    fi
else
    echo 'extension="apc.so"' >> $INI_PATH
fi

echo 'apc.enabled=1' >> $INI_PATH
echo 'apc.enable_cli=1' >> $INI_PATH
