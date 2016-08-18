sudo kill -9 `sudo lsof -t -i:11211` # kill listeners on required port
docker run -d -p 11211:11211 memcached

pecl uninstall memcached

# install ext-memcached requirements:
# "error: memcached support requires ZLIB"
# "error: memcached support requires libmemcached"
sudo apt-get -y install zlib1g-dev libmemcached-dev
printf "no --disable-memcached-sasl\n" | pecl install memcached
