---
layout: interface
title: PSR-16 simplecache
description: psr/simplecache is a PHP-FIG (Framework Interop Group) standard for a driver model cache implementation. It's a relatively simple & straightforward driver model interface, very similar to KeyValueStore.
weight: 2
icon: fa fa-gear
namespace: MatthiasMullie\Scrapbook\Psr16
---

```php
// boilerplate code example with Memcached, but any
// MatthiasMullie\Scrapbook\KeyValueStore adapter will work
$client = new \Memcached();
$client->addServer('localhost', 11211);
$cache = new \MatthiasMullie\Scrapbook\Adapters\Memcached($client);

// create Simplecache object from cache engine
$simplecache = new \MatthiasMullie\Scrapbook\Psr16\SimpleCache($cache);

// get value from cache
$value = $simplecache->get('key');

// ... or store a new value to cache
$simplecache->set('key', 'updated-value');
```

<hr class="sep10">

[psr/simplecache](https://github.com/php-fig/fig-standards/blob/master/proposed/simplecache.md)
is an attempt to standardize how cache is implemented across frameworks &
libraries in the PHP landscape.

It's a relatively simple & straightforward driver model interface. It's very
similar to KeyValueStore, but supports fewer features (e.g. CAS is missing).

This project bridges the gap between KeyValueStore based adapters & extras, and
PSR-16: any of the Scrapbook tools are accessible in a PSR-16 compatible manner.

<hr class="sep20">

## Methods

<hr class="sep10">

{% include psr-16-cache.html %}
{% include psr-16-counter.html %}
