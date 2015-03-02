---
layout: adapter
title: MemoryStore
description: PHP can keep data in memory, too! PHP arrays as storage are particularly useful to run tests against, since you don't have to install any other service.
weight: 8
image: memory.jpg
homepage:
project: scrapbook/key-value-store
class: Scrapbook\Adapters\MemoryStore
---

```php
// create Scrapbook cache object
$cache = new \Scrapbook\Adapters\MemoryStore();

// set a value
$cache->set('key', 'value'); // returns true

// get a value
$cache->get('key'); // returns 'value'
```

<hr class="sep20">
