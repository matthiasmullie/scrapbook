---
layout: adapter
title: APC
description: APC is a free and open opcode cache for PHP. Its goal is to provide a free, open, and robust framework for caching and optimizing PHP intermediate code.
weight: 3
image: apc.jpg
homepage: http://php.net/manual/en/book.apc.php
class: MatthiasMullie\Scrapbook\Adapters\Apc
---

```php
// create Scrapbook KeyValueStore object
$cache = new \MatthiasMullie\Scrapbook\Adapters\Apc();

// set a value
$cache->set('key', 'value'); // returns true

// get a value
$cache->get('key'); // returns 'value'
```

With APC, there is no "cache server", the data is just cached on the executing
machine and available to all PHP processes on that machine. The PECL
[APC](https://pecl.php.net/package/APC) or [APCu](https://pecl.php.net/package/APCu)
extensions can be used.

<hr class="sep20">
