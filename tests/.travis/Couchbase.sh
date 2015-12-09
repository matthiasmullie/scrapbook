INI_PATH=`php -r "echo php_ini_loaded_file();"`

untilsuccessful() {
    "$@"
    while [ $? -ne 0 ]
    do
        echo Retrying...
        sleep 1
        "$@"
    done
}

docker run -d -p 11212:11211 couchbase
ID=`docker ps -l -q` # last container's id
untilsuccessful docker exec -it $ID /opt/couchbase/bin/couchbase-cli cluster-init -c localhost:8091 -u Administrator -p password --cluster-init-ramsize=256
untilsuccessful docker exec -it $ID /opt/couchbase/bin/couchbase-cli bucket-create -c localhost:8091 -u Administrator -p password --bucket=default --bucket-password= --bucket-type=couchbase --bucket-port=11211 --bucket-ramsize=100 --bucket-replica=0 --enable-flush=1

sudo wget -O/etc/apt/sources.list.d/couchbase.list http://packages.couchbase.com/ubuntu/couchbase-ubuntu1204.list
sudo wget -O- http://packages.couchbase.com/ubuntu/couchbase.key | sudo apt-key add -
sudo apt-get update
sudo apt-get install -y libcouchbase2-libevent libcouchbase-dev
# install older ext-couchbase version - flush() in newer fails
pecl install couchbase-2.0.5

echo 'extension="couchbase.so"' >> $INI_PATH
