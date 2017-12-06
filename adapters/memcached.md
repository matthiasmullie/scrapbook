---
layout: adapter
title: Memcached
description: Memcached is an in-memory key-value store for small chunks of arbitrary data (strings, objects) from results of database calls, API calls, or page rendering.
weight: 0
image: memcached.jpg
homepage: https://memcached.org
class: MatthiasMullie\Scrapbook\Adapters\Memcached
---

```php
// create \Memcached object pointing to your Memcached server
$client = new \Memcached();
$client->addServer('localhost', 11211);
// create Scrapbook KeyValueStore object
$cache = new \MatthiasMullie\Scrapbook\Adapters\Memcached($client);

// set a value
$cache->set('key', 'value'); // returns true

// get a value
$cache->get('key'); // returns 'value'
```

The [PECL Memcached extension](https://pecl.php.net/package/memcached) is used
to interface with the Memcached server.
Just provide a valid `\Memcached` object to the Memcached adapter:

<hr class="sep20">
