---
layout: adapter
title: Couchbase
description: Engineered to meet the elastic scalability, consistent high performance, always-on availability, and data mobility requirements of mission critical applications.
weight: 2
image: couchbase.jpg
homepage: https://www.couchbase.com
class: MatthiasMullie\Scrapbook\Adapters\Couchbase
---

```php
// create \CouchbaseBucket object pointing to your Couchbase server
$cluster = new \CouchbaseCluster('couchbase://localhost');
$bucket = $cluster->openBucket('default');
// create Scrapbook KeyValueStore object
$cache = new \MatthiasMullie\Scrapbook\Adapters\Couchbase($bucket);

// set a value
$cache->set('key', 'value'); // returns true

// get a value
$cache->get('key'); // returns 'value'
```

The [PECL Couchbase extension](https://pecl.php.net/package/couchbase) is used
to interface with the Couchbase server. Just provide a valid `\CouchbaseBucket`
object to the Couchbase adapter:

<hr class="sep20">
