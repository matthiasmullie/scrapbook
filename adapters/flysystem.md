---
layout: adapter
title: Flysystem
description: While it's not the fastest kind of cache in terms of I/O access times, opening a file usually still beats redoing expensive computations.
weight: 7
image: /public/adapters/flysystem.jpg
homepage:
class: MatthiasMullie\Scrapbook\Adapters\Flysystem
composer: [ 'composer require league/flysystem' ]
---

```php
// create Flysystem object
$adapter = new \League\Flysystem\Local\LocalFilesystemAdapter('/path/to/cache', null, LOCK_EX);
$filesystem = new \League\Flysystem\Filesystem($adapter);

// create Scrapbook KeyValueStore object
$cache = new \MatthiasMullie\Scrapbook\Adapters\Flysystem($filesystem);

// set a value
$cache->set('key', 'value'); // returns true

// get a value
$cache->get('key'); // returns 'value'
```

The filesystem-based adapter uses `league\flysystem` to abstract away the file
operations, and will work with all kinds of storage that `league\filesystem`
provides.

<hr class="sep20">
