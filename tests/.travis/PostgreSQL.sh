psql -c 'create database cache;' -U postgres

if [[ "$TRAVIS_PHP_VERSION" != "hhvm" ]]
then
    echo 'extension="pgsql.so"' >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini
else
    sudo add-apt-repository -y ppa:ubuntu-toolchain-r/test
    sudo apt-get update -qq
    sudo apt-get install -qq libstdc++6

    sudo wget https://github.com/PocketRent/hhvm-pgsql/raw/releases/3.6.0/ubuntu/precise/pgsql.so

    sudo mkdir /hhvm-extensions/
    sudo mv pgsql.so /hhvm-extensions

    echo "hhvm.dynamic_extension_path=/hhvm-extensions" >> /etc/hhvm/php.ini
    echo "hhvm.dynamic_extensions[pgsql]=pgsql.so" >> /etc/hhvm/php.ini
fi
