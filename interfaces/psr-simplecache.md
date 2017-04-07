---
layout: interface
title: PSR-16 simple-cache
description: psr/simple-cache is a PHP-FIG (Framework Interop Group) standard for a driver model cache implementation. It's a relatively simple & straightforward driver model interface, very similar to KeyValueStore.
weight: 2
icon: fa fa-gear
class: MatthiasMullie\Scrapbook\Psr16\SimpleCache
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

[PSR-16](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-16-simple-cache.md)
(a PHP-FIG standard) is a second PHP-FIG cache standard. It's a driver model
just like KeyValueStore, and it works very much in the same way.

It doesn't let you do too many operations. If `get`, `set`, `delete` (and their
*multi counterparts) and `delete` is all you need, you're probably better off
using this (or psr/cache) as this interface is also supported by other cache
libraries.

This interface bridges the gap between KeyValueStore based adapters & features,
and PSR-16: any of the Scrapbook tools are accessible in a PSR-16 compatible
manner.

<hr class="sep20">

## Methods

<hr class="sep10">

{% include psr-16-cache.html %}
