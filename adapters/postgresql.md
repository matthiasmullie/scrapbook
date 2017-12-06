---
layout: adapter
title: PostgreSQL
description: PostgreSQL has a proven architecture that has earned it a strong reputation for reliability, data integrity, and correctness.
weight: 6
image: /public/adapters/postgresql.jpg
homepage: https://www.postgresql.org
class: MatthiasMullie\Scrapbook\Adapters\PostgreSQL
---

```php
// create \PDO object pointing to your PostgreSQL server
$client = new PDO('pgsql:user=postgres dbname=cache password=');
// create Scrapbook KeyValueStore object
$cache = new \MatthiasMullie\Scrapbook\Adapters\PostgreSQL($client);

// set a value
$cache->set('key', 'value'); // returns true

// get a value
$cache->get('key'); // returns 'value'
```

While a database is not a genuine cache, it can also serve as key-value store.
Just don't expect the same kind of performance you'd expect from a dedicated
cache server.

But there could be a good reason to use a database-based cache: it's convenient
if you already use a database and it may have other benefits (like persistent
storage, replication.)

<hr class="sep20">
