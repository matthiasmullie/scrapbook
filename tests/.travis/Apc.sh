if [[ "$TRAVIS_PHP_VERSION" != "hhvm" ]]
then
    if [[ `php-config --vernum` -ge 50500 ]]
    then
        pecl config-set preferred_state beta
        printf "yes\n"
        pecl install apcu
    else
        echo 'extension="apc.so"' >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini
    fi
    echo 'apc.enabled=1' >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini
    echo 'apc.enable_cli=1' >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini
else
    echo 'extension="apc.so"' >> /etc/hhvm/php.ini
    echo 'apc.enabled=1' >> /etc/hhvm/php.ini
    echo 'apc.enable_cli=1' >> /etc/hhvm/php.ini
fi
