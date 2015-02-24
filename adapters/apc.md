---
layout: adapter
title: APC
description: APC is a free and open opcode cache for PHP. Its goal is to provide a free, open, and robust framework for caching and optimizing PHP intermediate code. 
weight: 2
image: apc.jpg
homepage: http://php.net/manual/en/book.apc.php
project: scrapbook/apc
class: Scrapbook\Adapters\Apc
---

```php
// create Scrapbook cache object
$cache = new \Scrapbook\Adapters\Apc();

// set a value
$cache->set('key', 'value'); // returns true

// get a value
$cache->get('key'); // returns 'value'
```

<hr class="sep20">
