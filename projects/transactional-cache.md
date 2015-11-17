---
layout: project
title: Transactional cache
description: TransactionalStore makes it possible to defer writes to a later point in time. Similar to transactions in databases, all deferred writes can be rolled back or committed all at once to ensure the data that is stored is reliable and complete. All of it will be stored, or nothing at all.
weight: 3
icon: ss-index
class: MatthiasMullie\Scrapbook\Buffered\TransactionalStore
---

```php
// boilerplate code example with Memcached, but any
// MatthiasMullie\Scrapbook\KeyValueStore adapter will work
$client = new \Memcached();
$client->addServer('localhost', 11211);
$cache = new \MatthiasMullie\Scrapbook\Adapters\Memcached($client);

// create transactional cache layer over our real cache
$buffered = new \MatthiasMullie\Scrapbook\Buffered\TransactionalStore($cache);

// begin a transaction
$cache->begin();

// set a value
// it won't be stored in real cache until commit() is called
$cache->set('key', 'value'); // returns true

// get a value
// it won't get it from memcached (where it is not yet set), it'll be read from
// PHP memory
$cache->get('key'); // returns 'value'

// now commit write operations, this will effectively propagate the update to
// 'key' to memcached (or whatever adapter we decide to use)
$cache->commit();
```

TransactionalStore is similar to BufferedStore. It wraps around any
KeyValueStore, but provides that one with transactional capabilities. It makes
it possible to defer writes to a later point in time.

You may want to process code throughout your codebase, but not commit it any
changes until everything has successfully been validated & written to permanent
storage.

TransactionalStore lets you start a transaction, which will defer all writes
until you're actually ready to do them (or roll them back). Meanwhile, it keeps
all your changes around in a local cache, so follow-up operations on your cache
operate on the correct date, even though it hasn't yet been committed to your
real cache.

It too is a KeyValueStore, but adds 3 methods:

<h3 class="headline">begin()</h3>
<span class="brd-headling"></span>
<div class="clearfix"></div>

Initiate a transaction: this will defer all writes to real cache until
commit() is called.

Transactions can be nested. A new transaction can be begin while another is
already in progress. Committing the nested transaction will apply the changes
to the one that was already in progress. Changes will only be committed to
cache once the original transaction is committed.
Rolling back a nested transaction will only roll back those changes and leave
changes in the parent transaction alone.

<h3 class="headline">commit(): bool</h3>
<span class="brd-headling"></span>
<div class="clearfix"></div>

Commits the deferred updates to real cache.
If the any write fails, all subsequent writes will be aborted & all keys
that had already been written to will be restored to their original value.

<h3 class="headline">rollback()</h3>
<span class="brd-headling"></span>
<div class="clearfix"></div>

Roll back all scheduled changes.
