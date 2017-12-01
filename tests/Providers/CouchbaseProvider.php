<?php

namespace MatthiasMullie\Scrapbook\Tests\Providers;

use MatthiasMullie\Scrapbook\Exception\Exception;
use MatthiasMullie\Scrapbook\Tests\AdapterProvider;

class CouchbaseProvider extends AdapterProvider
{
    public function __construct()
    {
        if (!class_exists('CouchbaseCluster')) {
            throw new Exception('ext-couchbase is not installed.');
        }

        $authenticator = new \Couchbase\PasswordAuthenticator();
        $authenticator->username('Administrator')->password('password');

        $cluster = new \CouchbaseCluster('couchbase://couchbase:11210?detailed_errcodes=1');
        $cluster->authenticate($authenticator);
        $bucket = $cluster->openBucket('default');

        // wait 10 seconds should nodes not be healthy; they may be warming up
        for ($i = 0; $i < 10; $i++) {
            $healthy = true;
            $info = $bucket->manager()->info();
            foreach ($info['nodes'] as $node) {
                $healthy = $healthy && $node['status'] === 'healthy';
            }
            if (!$healthy) {
                sleep(1);
            }
        }

        parent::__construct(new \MatthiasMullie\Scrapbook\Adapters\Couchbase($bucket));
    }
}
