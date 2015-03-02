---
layout: adapter
title: MySQL
description: MySQL is the world's most popular open source database. MySQL can cost-effectively help you deliver high performance, scalable database applications.
weight: 4
image: mysql.jpg
homepage: http://www.mysql.com
project: scrapbook/sql
class: Scrapbook\Adapters\MySQL
---

```php
// create \PDO object pointing to your MySQL server
$client = new PDO('mysql:dbname=cache;host=127.0.0.1', 'root', '');
// create Scrapbook cache object
$cache = new \Scrapbook\Adapters\MySQL($client);

// set a value
$cache->set('key', 'value'); // returns true

// get a value
$cache->get('key'); // returns 'value'
```

<hr class="sep20">
