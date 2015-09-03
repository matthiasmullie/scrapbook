if [[ "$TRAVIS_PHP_VERSION" != "hhvm" ]]
then
    echo 'extension="memcached.so"' >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini
else
    echo 'extension="memcached.so"' >> /etc/hhvm/php.ini
fi
