if [[ "$TRAVIS_PHP_VERSION" != "hhvm" ]]
then
    INI_PATH=~/.phpenv/versions/$(phpenv version-name)/etc/php.ini

    if [[ `php-config --vernum` -ge 70000 ]] # PHP>=7.0
    then
        pecl config-set preferred_state beta
        printf "yes\n" | pecl install apcu
    elif [[ `php-config --vernum` -ge 50500 ]] # 7.0>PHP>=5.5
    then
        printf "yes\n" | pecl install apcu-4.0.8
    else # PHP<5.5
        echo 'extension="apc.so"' >> $INI_PATH
    fi
else
    INI_PATH=/etc/hhvm/php.ini
    echo 'extension="apc.so"' >> $INI_PATH
fi

echo 'apc.enabled=1' >> $INI_PATH
echo 'apc.enable_cli=1' >> $INI_PATH
