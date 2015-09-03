if [ ! -d /etc/hhvm ]
then
    echo 'extension="redis.so"' >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini
else
    echo 'extension="redis.so"' >> /etc/hhvm/php.ini
fi
