---
layout: adapter
title: MemoryStore
description: PHP can keep data in memory, too! PHP arrays as storage are particularly useful to run tests against, since you don't have to install any other service.
weight: 8
image: /public/adapters/memory.jpg
homepage:
class: MatthiasMullie\Scrapbook\Adapters\MemoryStore
---

```php
// create Scrapbook KeyValueStore object
$cache = new \MatthiasMullie\Scrapbook\Adapters\MemoryStore();

// set a value
$cache->set('key', 'value'); // returns true

// get a value
$cache->get('key'); // returns 'value'
```

Stuffing values in memory is mostly useless: they'll drop out at the end of the
request. Unless you're using these cached values more than once in the same
request, cache will always be empty and there's little reason to use this cache.

However, it is extremely useful when unittesting: you can run your entire test
suite on this memory-based store, instead of setting up cache services and
making sure they're in a pristine state...

<hr class="sep20">
