---
layout: project
title: Taggable cache
description: A PSR-cache implementation that allows you to tag related items & clear cached data for only that tag. It's an implementation of cache/taggable-cache's traits that will enable it to work with all (and not just Scrapbook's) PSR-cache compliant implementations.
weight: 6
icon: fa fa-tag
namespace: MatthiasMullie\Scrapbook\Psr6\Taggable
composer: [ 'composer require cache/taggable-cache:~0.3' ]
---

```php
// create PSR-6 Pool object using matthiasmullie/scrapbook
$cache = new \MatthiasMullie\Scrapbook\Adapters\MemoryStore();
$pool = new \MatthiasMullie\Scrapbook\Psr6\Pool($cache);

// wrap the PSR-6 pool inside a taggable cache object
$taggablePool = new \MatthiasMullie\Scrapbook\Psr6\Taggable\Pool($pool);

// tag & save some data
$item = $taggablePool->getItem('tobias', ['developer', 'speaker']);
$item->set('foobar');
$taggablePool->save($item);

// fetch results
$taggablePool->getItem('tobias', ['speaker', 'developer'])->isHit(); // true
$taggablePool->getItem('tobias', ['developer'])->isHit(); // false

// and clear by tag
$taggablePool->clear(['nice guy']);
$taggablePool->getItem('tobias', ['developer', 'speaker'])->isHit(); // true
```

<hr class="sep10">

[cache/taggable-cache](https://github.com/php-cache/taggable-cache) is a project
to help [psr/cache](https://github.com/php-fig/fig-standards/blob/master/proposed/cache.md)
compliant projects implement tagging into their cache.

Like a lot of other psr/cache implementations, Scrapbook's version
(purposefully) does not directly implement cache/taggable-cache: the goal is
only to provide an exact psr/cache implementation. Nothing more, nothing less,
or there's no point in having a standard interface when people are using
non-standard features.

This project implements cache/taggable-cache in a way that lets you wrap it
around every possible PSR-cache (including Scrapbook's), regardless of whether
or not they've implemented the cache/taggable-cache traits. That's the beauty
of having a standardized psr/cache interface across multiple projects.
