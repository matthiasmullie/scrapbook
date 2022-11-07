---
layout: adapter
title: Couchbase
description: Engineered to meet the elastic scalability, consistent high performance, always-on availability, and data mobility requirements of mission critical applications.
weight: 2
image: /public/adapters/couchbase.jpg
homepage: https://www.couchbase.com
class: MatthiasMullie\Scrapbook\Adapters\Couchbase
---

```php
// create \Couchbase\Bucket object pointing to your Couchbase server
$options = new \Couchbase\ClusterOptions();
$options->credentials('username', 'password');
$cluster = new \Couchbase\Cluster('couchbase://localhost', $options);
$bucket = $cluster->bucket('default');
$collection = $bucket->defaultCollection();
$bucketManager = $cluster->buckets();
// create Scrapbook KeyValueStore object
$cache = new \MatthiasMullie\Scrapbook\Adapters\Couchbase($collection, $bucketManager, $bucket);

// set a value
$cache->set('key', 'value'); // returns true

// get a value
$cache->get('key'); // returns 'value'
```

The [PECL Couchbase extension](https://pecl.php.net/package/couchbase) and
[couchbase/couchbase package](https://packagist.org/packages/couchbase/couchbase)
are used to interface with the Couchbase server. Just provide valid
`\Couchbase\Collection`, `\Couchbase\Management\BucketManager` and
`\Couchbase\Bucket` objects to the Couchbase adapter:

<hr class="sep20">
