---
layout: adapter
title: Flysystem
description: While it's not the fastest kind of cache in terms of I/O access times, opening a file usually still beats redoing expensive computations.
weight: 7
image: flysystem.jpg
homepage:
class: MatthiasMullie\Scrapbook\Adapters\Flysystem
composer: [ 'composer require league/flysystem' ]
---

The filesystem-based adapter uses `league\flysystem` to abstract away the file
operations, and will work with all kinds of storage that `league\filesystem`
provides.

```php
// create Flysystem object
$adapter = new \League\Flysystem\Adapter\Local('/path/to/cache', LOCK_EX);
$filesystem = new \League\Flysystem\Filesystem($adapter);

// create Scrapbook KeyValueStore object
$cache = new \MatthiasMullie\Scrapbook\Adapters\Flysystem($filesystem);

// set a value
$cache->set('key', 'value'); // returns true

// get a value
$cache->get('key'); // returns 'value'
```

<hr class="sep20">
