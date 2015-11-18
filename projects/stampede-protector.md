---
layout: project
title: Stampede protector
description: A cache stampede happens when there are a lot of requests for data that is not currently in cache, causing a lot of concurrent complex operations. Stampede protector will make sure only the first process executes and the other processes just wait, instead of crippling the server.
weight: 4
icon: fa fa-pause
class: MatthiasMullie\Scrapbook\Scale\StampedeProtector
---

```php
// boilerplate code example with Memcached, but any
// MatthiasMullie\Scrapbook\KeyValueStore adapter will work
$client = new \Memcached();
$client->addServer('localhost', 11211);
$cache = new \MatthiasMullie\Scrapbook\Adapters\Memcached($client);

// create stampede protector layer over our real cache
$protector = new \MatthiasMullie\Scrapbook\Scale\StampedeProtector($cache);

// get a value
// if it's stampede-protected, it'll just take a little longer to return,
// otherwise there won't be any difference
$protector->get('key'); // returns 'value'
```


A cache stampede happens when there are a lot of requests for data that is not
currently in cache. Examples could be:

* cache expires for something that is often under very heavy load
* sudden unexpected high load on something that is likely to not be in cache

In those cases, this huge amount of requests for data that is not at that time
in cache, causes that expensive operation to be executed a lot of times, all at
once.

StampedeProtector is designed counteract that. If a value can't be found in
cache, something will be stored to another key to indicate it was requested but
didn't exist. Every follow-up request for a short period of time will find that
indication and know another process is already generating that result, so those
will just wait until it becomes available, instead of crippling the servers.

StampedeProtector just wraps around any KeyValueStore and is itself a
KeyValueStore. Just use it like you would call any cache, but enjoy the stampede
protection! Just make sure to always store data to the caches you read from when
they turn up empty.
