untilsuccessful() {
    "$@"
    while [ $? -ne 0 ]
    do
        echo Retrying...
        sleep 1
        "$@"
    done
}

sudo kill -9 `sudo lsof -t -i:8091,11210,11211` # kill listeners on required ports
docker run -d -p 8091:8091 -p 11210:11210 -p 11211:11211 couchbase
ID=`docker ps -l -q` # last container's id
untilsuccessful docker exec -it $ID /opt/couchbase/bin/couchbase-cli cluster-init -c localhost:8091 -u Administrator -p password --cluster-ramsize=2000
untilsuccessful docker exec -it $ID /opt/couchbase/bin/couchbase-cli bucket-create -c localhost:8091 -u Administrator -p password --bucket=default --bucket-password= --bucket-type=memcached --bucket-port=11211 --bucket-ramsize=1500 --bucket-replica=0 --enable-flush=1 --wait

pecl uninstall couchbase

sudo wget -O/etc/apt/sources.list.d/couchbase.list http://packages.couchbase.com/ubuntu/couchbase-ubuntu1204.list
sudo wget -O- http://packages.couchbase.com/ubuntu/couchbase.key | sudo apt-key add -
sudo apt-get update
sudo apt-get install -y libcouchbase2-libevent libcouchbase-dev
pecl install couchbase
