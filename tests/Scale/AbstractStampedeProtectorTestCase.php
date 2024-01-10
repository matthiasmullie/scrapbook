<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Tests\Scale;

use MatthiasMullie\Scrapbook\Adapters\Apc;
use MatthiasMullie\Scrapbook\Adapters\Couchbase;
use MatthiasMullie\Scrapbook\Adapters\Memcached;
use MatthiasMullie\Scrapbook\Adapters\MemoryStore;
use MatthiasMullie\Scrapbook\Adapters\Redis;
use MatthiasMullie\Scrapbook\Adapters\SQL;
use MatthiasMullie\Scrapbook\KeyValueStore;
use MatthiasMullie\Scrapbook\Tests\AbstractKeyValueStoreTestCase;

abstract class AbstractStampedeProtectorTestCase extends AbstractKeyValueStoreTestCase
{
    /**
     * Time (in milliseconds) to protect against stampede.
     *
     * @var int
     */
    protected const SLA = 100;

    public function getTestKeyValueStore(KeyValueStore $keyValueStore): KeyValueStore
    {
        return new StampedeProtectorStub($keyValueStore, static::SLA);
    }

    public function testStampedeGetExisting(): void
    {
        $this->adapterKeyValueStore->set('key', 'value');

        /*
         * Verify that we WERE able to fetch the value, DIDN'T wait & DIDN'T
         * create a tmp "stampede" indicator file.
         */
        $this->assertEquals('value', $this->testKeyValueStore->get('key'));
        $this->assertEquals(0, $this->testKeyValueStore->count);
        $this->assertFalse($this->adapterKeyValueStore->get('key.stampede'));
    }

    public function testStampedeGetNonExisting(): void
    {
        /*
         * Verify that we WEREN'T able to fetch the value, DIDN'T wait & DID
         * create a tmp "stampede" indicator file.
         */
        $this->assertFalse($this->testKeyValueStore->get('key'));
        $this->assertEquals(0, $this->testKeyValueStore->count);
        $this->assertEquals('', $this->adapterKeyValueStore->get('key.stampede'));
    }

    public function testStampedeGetStampede(): void
    {
        if (!$this->isForkable()) {
            $this->markTestSkipped("Can't test stampede without forking");
        }

        $pid = pcntl_fork();
        if ($pid === -1) {
            // can't fork, ignore this test...
        } elseif ($pid === 0) {
            // request non-existing key: this should make us go in stampede-
            // protection mode if another process/thread requests it again...
            $this->testKeyValueStore->get('key');

            // meanwhile, sleep for a small portion of the stampede-protection
            // time - this could be an expensive computation
            usleep(static::SLA / 10 * 1000 + 1);

            // now that we've computed the new value, store it!
            $this->testKeyValueStore->set('key', 'value');

            // exit child process, since I don't want the child to output any
            // test results (would be rather confusing to have output twice)
            exit;
        } else {
            /*
             * Thread execution is in OS scheduler's hands. We don't want to
             * start testing stampede protection until the other thread has done
             * the first request though, so let's wait a bit...
             */
            while ($this->adapterKeyValueStore->getMulti(['key', 'key.stampede']) === []) {
                usleep(10);
            }

            // verify that we WERE able to fetch the value, but had to wait for
            // some time because we were in stampede protection
            $this->assertEquals('value', $this->testKeyValueStore->get('key'));
            $this->assertGreaterThan(0, $this->testKeyValueStore->count);

            pcntl_waitpid(0, $status, WNOHANG);
        }
    }

    public function testStampedeGetMultiExisting(): void
    {
        $this->adapterKeyValueStore->setMulti(['key' => 'value', 'key2' => 'value2']);

        /*
         * Verify that we WERE able to fetch the values, DIDN'T wait & DIDN'T
         * create a tmp "stampede" indicator file.
         */
        $this->assertEquals(
            ['key' => 'value', 'key2' => 'value2'],
            $this->testKeyValueStore->getMulti(['key', 'key2'])
        );
        $this->assertEquals(0, $this->testKeyValueStore->count);
        $this->assertEquals(
            [],
            $this->adapterKeyValueStore->getMulti(['key.stampede', 'key2.stampede'])
        );
    }

    public function testStampedeGetMultiNonExisting(): void
    {
        /*
         * Verify that we WEREN'T able to fetch the values, DIDN'T wait & DID
         * create a tmp "stampede" indicator file.
         */
        $this->assertEquals(
            [],
            $this->testKeyValueStore->getMulti(['key', 'key2'])
        );
        $this->assertEquals(0, $this->testKeyValueStore->count);
        $this->assertEquals(
            ['key.stampede' => '', 'key2.stampede' => ''],
            $this->adapterKeyValueStore->getMulti(['key.stampede', 'key2.stampede'])
        );
    }

    public function testStampedeGetMultiExistingAndNonExisting(): void
    {
        $this->adapterKeyValueStore->set('key', 'value');

        /*
         * Verify that we WERE & WEREN'T able to fetch the values, DIDN'T wait &
         * DID create a tmp "stampede" indicator file for the missing value.
         */
        $this->assertEquals(
            ['key' => 'value'],
            $this->testKeyValueStore->getMulti(['key', 'key2'])
        );
        $this->assertEquals(0, $this->testKeyValueStore->count);
        $this->assertEquals(
            ['key2.stampede' => ''],
            $this->adapterKeyValueStore->getMulti(['key.stampede', 'key2.stampede'])
        );
    }

    public function testStampedeGetMultiStampede(): void
    {
        if (!$this->isForkable()) {
            $this->markTestSkipped("Can't test stampede without forking");
        }

        $this->adapterKeyValueStore->set('key2', 'value2');

        $pid = pcntl_fork();
        if ($pid === -1) {
            // can't fork, ignore this test...
        } elseif ($pid === 0) {
            // request non-existing key: this should make us go in stampede-
            // protection mode if another process/thread requests it again...
            $this->testKeyValueStore->getMulti(['key', 'key2']);

            // meanwhile, sleep for a small portion of the stampede-protection
            // time - this could be an expensive computation
            usleep(static::SLA / 10 * 1000 + 1);

            // now that we've computed the new value, store it!
            $this->testKeyValueStore->set('key', 'value');

            // exit child process, since I don't want the child to output any
            // test results (would be rather confusing to have output twice)
            exit;
        } else {
            /*
             * Thread execution is in OS scheduler's hands. We don't want to
             * start testing stampede protection until the other thread has done
             * the first request though, so let's wait a bit...
             */
            while ($this->adapterKeyValueStore->getMulti(['key', 'key.stampede']) === []) {
                usleep(10);
            }

            // verify that we WERE able to fetch the value, but had to wait for
            // some time because we were in stampede protection
            $this->assertEquals(
                ['key' => 'value', 'key2' => 'value2'],
                $this->testKeyValueStore->getMulti(['key', 'key2'])
            );
            $this->assertGreaterThan(0, $this->testKeyValueStore->count);

            pcntl_waitpid(0, $status, WNOHANG);
        }
    }

    public function testStampedeGetExistingAndProtected(): void
    {
        /*
         * this test is for a rare occurrence where one request protects a key
         * and resolves the value very fast but the other request reads both
         * the stampede and the value but still waits and it shouldn't
         */

        if (!$this->isForkable()) {
            $this->markTestSkipped("Can't test stampede without forking");
        }

        $this->adapterKeyValueStore->set('key', 'value');
        $this->adapterKeyValueStore->set('key.stampede', '');

        $pid = pcntl_fork();
        if ($pid === -1) {
            // can't fork, ignore this test...
        } elseif ($pid === 0) {
            // request non-existing key: this should make us go in stampede-
            // protection mode if another process/thread requests it again...
            $this->testKeyValueStore->get('key');

            // meanwhile, sleep for a small portion of the stampede-protection
            // time - this could be an expensive computation
            usleep(static::SLA / 10 * 1000 + 1);

            // now that we've computed the new value, store it!
            $this->testKeyValueStore->set('key', 'value');

            // exit child process, since I don't want the child to output any
            // test results (would be rather confusing to have output twice)
            exit;
        } else {
            /*
             * Thread execution is in OS scheduler's hands. We don't want to
             * start testing stampede protection until the other thread has done
             * the first request though, so let's wait a bit...
             */
            while ($this->adapterKeyValueStore->getMulti(['key', 'key.stampede']) === []) {
                usleep(10);
            }

            // verify that we WERE able to fetch the value, and we did not wait
            $this->assertEquals('value', $this->testKeyValueStore->get('key'));
            $this->assertEquals(0, $this->testKeyValueStore->count);

            pcntl_waitpid(0, $status, WNOHANG);
        }
    }

    /**
     * Forking the parent is process is the only way to accurately test
     * concurrent requests. However, forking comes with its own set of problems,
     * so we may not want to do it in a bunch of cases.
     */
    protected function isForkable(): bool
    {
        if (!function_exists('pcntl_fork')) {
            return false;
        }

        // Now we know $cache will be just fine when forked, but we may be
        // running tests against multiple adapters & not all of them may be fine
        if (!$this->isForkableAdapter($this->adapterKeyValueStore)) {
            return false;
        }

        return true;
    }

    /**
     * Not all adapters (well, most actually) will handle forking well.
     * E.g. connections will be terminated as soon as child ends, ...
     */
    protected function isForkableAdapter(KeyValueStore $cache): bool
    {
        // MemoryStore can't share its "cache" (which is a PHP array) across
        // processes. Not only does stampede protection make no sense here, we
        // can't even properly test it.
        if ($cache instanceof MemoryStore) {
            return false;
        }

        // Memcached may become unreliable when forked
        // https://gist.github.com/matthiasmullie/e5e856b27ddb68d7cf80
        if ($cache instanceof Memcached) {
            return false;
        }

        // Couchbase, or at least in the config we're using it for tests, is
        // just like Memcached...
        if ($cache instanceof Couchbase) {
            return false;
        }

        // php-redis is known to exhibit connection issues because of pcntl_fork
        // https://github.com/phpredis/phpredis/issues/474
        if ($cache instanceof Redis) {
            return false;
        }

        // PDO connection is closed as soon as first thread finishes
        // http://php.net/manual/en/function.pcntl-fork.php#70721
        // https://bugs.php.net/bug.php?id=62571
        if ($cache instanceof SQL) {
            return false;
        }

        // Looks like Apc, threading & HHVM are not the greatest combo either...
        // https://travis-ci.org/matthiasmullie/scrapbook/jobs/91360815
        if ($cache instanceof Apc && defined('HHVM_VERSION')) {
            return false;
        }

        return true;
    }
}
