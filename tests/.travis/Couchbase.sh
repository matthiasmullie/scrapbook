sudo wget -O/etc/apt/sources.list.d/couchbase.list http://packages.couchbase.com/ubuntu/couchbase-ubuntu1204.list
sudo wget -O- http://packages.couchbase.com/ubuntu/couchbase.key | sudo apt-key add -
sudo apt-get update
sudo apt-get install libcouchbase2-libevent libcouchbase-dev libssl0.9.8
sudo wget http://packages.couchbase.com/releases/3.1.0/couchbase-server-enterprise_3.1.0-ubuntu12.04_amd64.deb
sudo dpkg -i couchbase-server-enterprise_3.1.0-ubuntu12.04_amd64.deb
sudo service couchbase-server start
/opt/couchbase/bin/couchbase-cli cluster-init -c 127.0.0.1:8091 -u Administrator -p password --cluster-init-ramsize=1024
/opt/couchbase/bin/couchbase-cli bucket-create -c 127.0.0.1:8091 -u Administrator -p password --bucket=default --bucket-password= --bucket-type=couchbase --bucket-port=11211 --bucket-ramsize=100 --bucket-replica=0 --enable-flush=1
pecl install couchbase
