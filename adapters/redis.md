---
layout: adapter
title: Redis
description: Redis is often referred to as a data structure server since keys can contain strings, hashes, lists, sets, sorted sets, bitmaps and hyperloglogs.
weight: 1
image: redis.jpg
homepage: http://redis.io
class: MatthiasMullie\Scrapbook\Adapters\Redis
---

```php
// create \Redis object pointing to your Redis server
$client = new \Redis();
$client->connect('127.0.0.1');
// create Scrapbook cache object
$cache = new \MatthiasMullie\Scrapbook\Adapters\Redis($client);

// set a value
$cache->set('key', 'value'); // returns true

// get a value
$cache->get('key'); // returns 'value'
```

<hr class="sep20">
