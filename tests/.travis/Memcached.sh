if [ ! -d /etc/hhvm ]
then
    echo 'extension="memcached.so"' >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini
else
    echo 'extension="memcached.so"' >> /etc/hhvm/php.ini
fi
