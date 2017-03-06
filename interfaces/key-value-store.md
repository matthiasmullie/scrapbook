---
layout: interface
title: Key-value store
description: An interface for typical key-value store functionality inspired by PHP's Memcached API. Implementing this interface in an application means you get support for every backend for free, since all adapters share this exact same implementation.
weight: 0
icon: ss-layergroup
class: MatthiasMullie\Scrapbook\KeyValueStore
---

```php
// boilerplate code example with Memcached...
$client = new \Memcached();
$client->addServer('localhost', 11211);
// create Scrapbook KeyValueStore object
$cache = new \MatthiasMullie\Scrapbook\Adapters\Memcached($client);

// set a value
$cache->set('key', 'value'); // returns true

// get a value
$cache->get('key'); // returns 'value'
```

KeyValueStore is the cornerstone of this project. It is the interface that
provides the most cache operations: `get`, `getMulti`, `set`, `setMulti`,
`delete`, `deleteMulti`, `add`, `replace`, `cas`, `increment`, `decrement`,
`touch` & `flush`.

If you've ever used Memcached before, KeyValueStore will look very similar,
since it's inspired by/modeled after that API.

All adapters & features implement this interface. If you have complex cache
needs (like being able to cas), this is the one to stick to.

<hr class="sep20">

## Methods

<hr class="sep10">

{% include key-value-store.html %}
