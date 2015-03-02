---
layout: adapter
title: Filesystem
description: While it's not the fastest kind of cache in terms of I/O access times, opening a file usually still beats redoing expensive computations.
weight: 7
image: filesystem.jpg
homepage:
project: scrapbook/filesystem
class: Scrapbook\Adapters\Filesystem
---

```php
// create Scrapbook cache object
$cache = new \Scrapbook\Adapters\Filesystem('/path/to/cache');

// set a value
$cache->set('key', 'value'); // returns true

// get a value
$cache->get('key'); // returns 'value'
```

<hr class="sep20">
