---
layout: extra
title: Shard
description: When you have too much data for (or requests to) 1 little server, this'll let you shard it over multiple cache servers. All data will automatically be distributed evenly across your server pool, so all the individual cache servers only get a fraction of the data & traffic.
weight: 3
icon: fa fa-arrows-alt
class: MatthiasMullie\Scrapbook\Scale\Shard
---

```php
// boilerplate code example with Redis, but any
// MatthiasMullie\Scrapbook\KeyValueStore adapter will work
$client = new \Redis();
$client->connect('192.168.1.100');
$cache = new \MatthiasMullie\Scrapbook\Adapters\Redis($client);

// a second Redis server...
$client2 = new \Redis();
$client2->connect('192.168.1.101');
$cache2 = new \MatthiasMullie\Scrapbook\Adapters\Redis($client);

// create shard layer over our real caches
// now $shard will automatically distribute the data across both servers
$shard = new \MatthiasMullie\Scrapbook\Scale\Shard($cache, $cache2);

// set a value
// it'll only be stored on 1 server
$shard->set('key', 'value'); // returns true

// get a value
// Shard will know what server it was stored to & will fetch it from there
$shard->get('key'); // returns 'value'
```


This class lets you scale your cache cluster by sharding the data across
multiple cache servers.

Pass the individual KeyValueStore objects that compose the cache server pool
into this constructor how you want the data to be sharded. The cache data
will be sharded over them according to the order they were in when they were
passed into this constructor (so make sure to always keep the order the same)

The sharding is spread evenly and all cache servers will roughly receive the
same amount of cache keys. If some servers are bigger than others, you can
offset this by adding the KeyValueStore object more than once.

Data can even be sharded among different adapters: one server in the shard
pool can be Redis while another can be Memcached. Not sure why you would even
want that, but you could!
