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

untilsuccessful /opt/couchbase/bin/couchbase-cli cluster-init --cluster=localhost:8091 --cluster-username=Administrator --cluster-password=password --cluster-ramsize=300
untilsuccessful /opt/couchbase/bin/couchbase-cli bucket-create --cluster=localhost:8091 --username=Administrator --password=password --bucket=default --bucket-type=couchbase --bucket-ramsize=200 --bucket-replica=0 --enable-flush=1 --wait

fg 1
