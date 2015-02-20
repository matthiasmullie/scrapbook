---
layout: project
title: PSR-6 cache
description: The PHP-FIG (Framework Interop Group) is working on setting a standard, called PSR-cache or PSR-6) for how to implement cache in PHP Frameworks & libraries. This is still work in progress and subject to change, but scrapbook has an implementation that builds on key-value-store, so it works with all adapters.
weight: 1
icon: fa fa-puzzle-piece
project: scrapbook/psr-cache
class: Scrapbook\Psr6
---

```php
// boilerplate code example with Memcached, but any
// Scrapbook\Cache\KeyValueStore adapter will work
$client = new \Memcached();
$client->addServer('localhost', 11211);
$cache = new \Scrapbook\Adapters\Memcached($client);

// create Pool object from cache engine
$pool = new \Scrapbook\Psr6\Pool($cache);

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

Judging from the current proposal, it will be a relatively simple &
straightforward interface, which will support fewer features than
scrapbook/key-value-store (e.g. CAS is missing). But it will - hopefully - make
for a less fragmented PHP cache landscape (which, yes, scrapbook is contributing
to.)

This project bridges the gap between scrapbook/key-value-store based adapters &
extras, and PSR-6: any of the scrapbook tools are accessible in a PSR-6
compatible manner.

PSR-6 is still work in progress. If it changes, so will this code.
