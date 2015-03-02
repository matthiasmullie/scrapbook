---
layout: adapter
title: Couchbase
description: Engineered to meet the elastic scalability, consistent high performance, always-on availability, and data mobility requirements of mission critical applications.
weight: 2
image: couchbase.jpg
homepage: http://www.couchbase.com
project: scrapbook/couchbase
class: Scrapbook\Adapters\Couchbase
---

```php
// create \CouchbaseBucket object pointing to your Couchbase server
$cluster = new \CouchbaseCluster('couchbase://localhost');
$bucket = $cluster->openBucket('default');
// create Scrapbook cache object
$cache = new \Scrapbook\Adapters\Couchbase($bucket);

// set a value
$cache->set('key', 'value'); // returns true

// get a value
$cache->get('key'); // returns 'value'
```

<hr class="sep20">
