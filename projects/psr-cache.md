---
layout: project
title: PSR-6 cache
description: The PHP-FIG (Framework Interop Group) have defined a standard (called PSR-6 or psr/cache) for how to implement cache in PHP Frameworks & libraries. Scrapbook has an implementation that builds on key-value-store, so it works with all adapters.
weight: 1
icon: fa fa-puzzle-piece
namespace: MatthiasMullie\Scrapbook\Psr6
---

```php
// boilerplate code example with Memcached, but any
// MatthiasMullie\Scrapbook\KeyValueStore adapter will work
$client = new \Memcached();
$client->addServer('localhost', 11211);
$cache = new \MatthiasMullie\Scrapbook\Adapters\Memcached($client);

// create Pool object from cache engine
$pool = new \MatthiasMullie\Scrapbook\Psr6\Pool($cache);

// get item from Pool
$item = $pool->getItem('key');

// get item value
$value = $item->get();

// ... or change the value & store it to cache
$item->set('updated-value');
$pool->save($item);
```

<hr class="sep10">

[PSR-cache](https://github.com/php-fig/fig-standards/blob/master/proposed/cache.md)
is an attempt to standardize how cache is implemented across frameworks &
libraries in the PHP landscape.

It's a relatively simple & straightforward interface, which supports fewer
features than MatthiasMullie\Scrapbook\KeyValueStore (e.g. CAS is missing).
But it will - hopefully - make for a less fragmented PHP cache landscape (which,
ironically, Scrapbook is contributing to.)

This project bridges the gap between MatthiasMullie\Scrapbook\KeyValueStore
based adapters & extras, and PSR-6: any of the Scrapbook tools are accessible
in a PSR-6 compatible manner.
