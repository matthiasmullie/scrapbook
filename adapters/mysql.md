---
layout: adapter
title: MySQL
description: MySQL is the world's most popular open source database. MySQL can cost-effectively help you deliver high performance, scalable database applications.
weight: 4
image: mysql.jpg
homepage: http://www.mysql.com
class: MatthiasMullie\Scrapbook\Adapters\MySQL
---

While a database is not a genuine cache, it can also serve as key-value store.
Just don't expect the same kind of performance you'd expect from a dedicated
cache server.

But there could be a good reason to use a database-based cache: it's convenient
if you already use a database and it may have other benefits (like persistent
storage, replication.)

```php
// create \PDO object pointing to your MySQL server
$client = new PDO('mysql:dbname=cache;host=127.0.0.1', 'root', '');
// create Scrapbook KeyValueStore object
$cache = new \MatthiasMullie\Scrapbook\Adapters\MySQL($client);

// set a value
$cache->set('key', 'value'); // returns true

// get a value
$cache->get('key'); // returns 'value'
```

<hr class="sep20">
