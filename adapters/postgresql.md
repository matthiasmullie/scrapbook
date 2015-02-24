---
layout: adapter
title: PostgreSQL
description: PostgreSQL has a proven architecture that has earned it a strong reputation for reliability, data integrity, and correctness.
weight: 5
image: postgresql.jpg
homepage: http://www.postgresql.org
project: scrapbook/sql
class: Scrapbook\Adapters\PostgreSQL
---

```php
// create \PDO object pointing to your PostgreSQL server
$client = new PDO('pgsql:user=postgres dbname=cache password=');
// create Scrapbook cache object
$cache = new \Scrapbook\Adapters\PostgreSQL($client);

// set a value
$cache->set('key', 'value'); // returns true

// get a value
$cache->get('key'); // returns 'value'
```

<hr class="sep20">
