#!/bin/bash

set -m

/entrypoint.sh couchbase-server &

untilsuccessful() {
    "$@"
    while [ $? -ne 0 ]; do
        sleep 1
        "$@"
    done
}

untilsuccessful /opt/couchbase/bin/couchbase-cli cluster-init -c localhost:8091 -u Administrator -p password --cluster-ramsize=300
untilsuccessful /opt/couchbase/bin/couchbase-cli bucket-create -c localhost:8091 -u Administrator -p password --bucket=default --bucket-port=11211 --bucket-password= --bucket-type=memcached --bucket-ramsize=200 --bucket-replica=0 --enable-flush=1 --wait

fg 1
