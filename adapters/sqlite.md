---
layout: adapter
title: SQLite
description: SQLite is a software library that implements a self-contained, serverless, zero-configuration, transactional SQL database engine.
weight: 5
image: sqlite.jpg
homepage: http://www.sqlite.org
class: MatthiasMullie\Scrapbook\Adapters\SQLite
---

```php
// create \PDO object pointing to your SQLite server
$client = new PDO('sqlite:cache.db');
// create Scrapbook cache object
$cache = new \MatthiasMullie\Scrapbook\Adapters\SQLite($client);

// set a value
$cache->set('key', 'value'); // returns true

// get a value
$cache->get('key'); // returns 'value'
```

<hr class="sep20">
