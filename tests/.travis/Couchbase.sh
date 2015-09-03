sudo wget -O/etc/apt/sources.list.d/couchbase.list http://packages.couchbase.com/ubuntu/couchbase-ubuntu1204.list
sudo wget -O- http://packages.couchbase.com/ubuntu/couchbase.key | sudo apt-key add -
sudo apt-get update
sudo apt-get install libcouchbase2-libevent libcouchbase-dev libssl0.9.8
# I'm going to install a pretty old couchbase version with memcached bucket-
# type (instead of couchbase) as that version won't allow me to
# --enable-flush (command unknown), but:
# * other 2.x versions time out trying to start the server
# * 3.x versions can't connect to server, once installed
sudo wget http://packages.couchbase.com/releases/2.0.1/couchbase-server-enterprise_x86_64_2.0.1.deb
sudo dpkg -i couchbase-server-enterprise_x86_64_2.0.1.deb
sudo service couchbase-server start
/opt/couchbase/bin/couchbase-cli cluster-init -c 127.0.0.1:8091 -u Administrator -p password --cluster-init-ramsize=1024
/opt/couchbase/bin/couchbase-cli bucket-create -c 127.0.0.1:8091 -u Administrator -p password --bucket=default --bucket-password= --bucket-type=memcached --bucket-port=11211 --bucket-ramsize=100 --bucket-replica=0 #--enable-flush=1
pecl install couchbase
