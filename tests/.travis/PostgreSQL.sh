INI_PATH=`php -r "echo php_ini_loaded_file();"`
PHP_VERSION=`php -r "echo phpversion();"`

psql -c 'create database cache;' -U postgres

if [[ $PHP_VERSION != *"hhvm" ]]
then
    echo 'extension="pgsql.so"' >> $INI_PATH
else
    # hhvm-dev, g++-4.8 libboost-dev, libpq-dev
    sudo add-apt-repository -y ppa:ubuntu-toolchain-r/test
    sudo apt-get update -qq
    sudo apt-get install -qq hhvm-dev g++-4.8 libboost-dev libpq-dev
    sudo update-alternatives --install /usr/bin/g++ g++ /usr/bin/g++-4.8 90

    # google-glog
    wget http://launchpadlibrarian.net/80433359/libgoogle-glog0_0.3.1-1ubuntu1_amd64.deb
    sudo dpkg -i libgoogle-glog0_0.3.1-1ubuntu1_amd64.deb
    rm libgoogle-glog0_0.3.1-1ubuntu1_amd64.deb
    wget http://launchpadlibrarian.net/80433361/libgoogle-glog-dev_0.3.1-1ubuntu1_amd64.deb
    sudo dpkg -i libgoogle-glog-dev_0.3.1-1ubuntu1_amd64.deb
    rm libgoogle-glog-dev_0.3.1-1ubuntu1_amd64.deb

    # jemalloc
    wget http://ubuntu.mirrors.tds.net/ubuntu/pool/universe/j/jemalloc/libjemalloc1_3.6.0-2_amd64.deb
    sudo dpkg -i libjemalloc1_3.6.0-2_amd64.deb
    rm libjemalloc1_3.6.0-2_amd64.deb
    wget http://ubuntu.mirrors.tds.net/ubuntu/pool/universe/j/jemalloc/libjemalloc-dev_3.6.0-2_amd64.deb
    sudo dpkg -i libjemalloc-dev_3.6.0-2_amd64.deb
    rm libjemalloc-dev_3.6.0-2_amd64.deb

    # version.h
    sudo wget -O /usr/include/hphp/runtime/version.h https://gist.githubusercontent.com/digitalkaoz/dee32e5e82fc776925cf/raw/d4c52e7841685275e105f26e6cfe27aa3662e85e/version.h

    # pgsql
    cd /tmp
    sudo wget https://github.com/PocketRent/hhvm-pgsql/archive/9d937c90ed43f649791fb91323e72015f3e5c915.tar.gz
    tar xzf 9d937c90ed43f649791fb91323e72015f3e5c915.tar.gz
    cd hhvm-pgsql-9d937c90ed43f649791fb91323e72015f3e5c915
    hphpize
    cmake .
    make

    # All of the above was just to compile a pgsql.so compatible with travis hhvm
    # version. We could use one of the pre-built extensions (e.g. link below), but
    # they might stop working when Travis changes HHVM version.
    # sudo wget https://github.com/PocketRent/hhvm-pgsql/raw/releases/3.6.0/ubuntu/precise/pgsql.so

    sudo mkdir /hhvm-extensions/
    sudo mv pgsql.so /hhvm-extensions

    echo "hhvm.dynamic_extension_path=/hhvm-extensions" >> $INI_PATH
    echo "hhvm.dynamic_extensions[pgsql]=pgsql.so" >> $INI_PATH
fi
