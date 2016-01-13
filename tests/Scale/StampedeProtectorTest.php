<?php

namespace MatthiasMullie\Scrapbook\Tests\Scale;

use MatthiasMullie\Scrapbook\Adapters\Apc;
use MatthiasMullie\Scrapbook\Adapters\Couchbase;
use MatthiasMullie\Scrapbook\Adapters\Memcached;
use MatthiasMullie\Scrapbook\Adapters\MemoryStore;
use MatthiasMullie\Scrapbook\Adapters\Redis;
use MatthiasMullie\Scrapbook\Adapters\SQL;
use MatthiasMullie\Scrapbook\KeyValueStore;
use MatthiasMullie\Scrapbook\Tests\AdapterProvider;
use MatthiasMullie\Scrapbook\Tests\AdapterTest;

class StampedeProtectorTest extends AdapterTest
{
    /**
     * Time (in milliseconds) to protect against stampede.
     *
     * @var int
     */
    const SLA = 100;

    /**
     * @var StampedeProtectorStub
     */
    protected $protector;

    public function setAdapter(KeyValueStore $adapter)
    {
        $this->cache = $adapter;
        $this->protector = new StampedeProtectorStub($adapter, static::SLA);
    }

    public function testGetExisting()
    {
        $this->cache->set('key', 'value');

        /*
         * Verify that we WERE able to fetch the value, DIDN'T wait & DIDN'T
         * create a tmp "stampede" indicator file.
         */
        $this->assertEquals('value', $this->protector->get('key'));
        $this->assertEquals(0, $this->protector->count);
        $this->assertEquals(false, $this->cache->get('key.stampede'));
    }

    public function testGetNonExisting()
    {
        /*
         * Verify that we WEREN'T able to fetch the value, DIDN'T wait & DID
         * create a tmp "stampede" indicator file.
         */
        $this->assertEquals(false, $this->protector->get('key'));
        $this->assertEquals(0, $this->protector->count);
        $this->assertEquals('', $this->cache->get('key.stampede'));
    }

    public function testGetStampede()
    {
        if (!$this->forkable()) {
            $this->markTestSkipped("Can't test stampede without forking");
        }

        $pid = pcntl_fork();
        if ($pid === -1) {
            // can't fork, ignore this test...
        } elseif ($pid === 0) {
            // request non-existing key: this should make us go in stampede-
            // protection mode if another process/thread requests it again...
            $this->protector->get('key');

            // meanwhile, sleep for a small portion of the stampede-protection
            // time - this could be an expensive computation
            usleep(static::SLA / 10 * 1000 + 1);

            // now that we've computed the new value, store it!
            $this->protector->set('key', 'value');

            // exit child process, since I don't want the child to output any
            // test results (would be rather confusing to have output twice)
            exit;
        } else {
            /*
             * Thread execution is in OS scheduler's hands. We don't want to
             * start testing stampede protection until the other thread has done
             * the first request though, so let's wait a bit...
             */
            while ($this->cache->getMulti(array('key', 'key.stampede')) === array()) {
                usleep(10);
            }

            // verify that we WERE able to fetch the value, but had to wait for
            // some time because we were in stampede protection
            $this->assertEquals('value', $this->protector->get('key'));
            $this->assertGreaterThan(0, $this->protector->count);

            pcntl_wait($status);
        }
    }

    public function testGetMultiExisting()
    {
        $this->cache->setMulti(array('key' => 'value', 'key2' => 'value2'));

        /*
         * Verify that we WERE able to fetch the values, DIDN'T wait & DIDN'T
         * create a tmp "stampede" indicator file.
         */
        $this->assertEquals(
            array('key' => 'value', 'key2' => 'value2'),
            $this->protector->getMulti(array('key', 'key2'))
        );
        $this->assertEquals(0, $this->protector->count);
        $this->assertEquals(
            array(),
            $this->cache->getMulti(array('key.stampede', 'key2.stampede'))
        );
    }

    public function testGetMultiNonExisting()
    {
        /*
         * Verify that we WEREN'T able to fetch the values, DIDN'T wait & DID
         * create a tmp "stampede" indicator file.
         */
        $this->assertEquals(
            array(),
            $this->protector->getMulti(array('key', 'key2'))
        );
        $this->assertEquals(0, $this->protector->count);
        $this->assertEquals(
            array('key.stampede' => '', 'key2.stampede' => ''),
            $this->cache->getMulti(array('key.stampede', 'key2.stampede'))
        );
    }

    public function testGetMultiExistingAndNonExisting()
    {
        $this->cache->set('key', 'value');

        /*
         * Verify that we WERE & WEREN'T able to fetch the values, DIDN'T wait &
         * DID create a tmp "stampede" indicator file for the missing value.
         */
        $this->assertEquals(
            array('key' => 'value'),
            $this->protector->getMulti(array('key', 'key2'))
        );
        $this->assertEquals(0, $this->protector->count);
        $this->assertEquals(
            array('key2.stampede' => ''),
            $this->cache->getMulti(array('key.stampede', 'key2.stampede'))
        );
    }

    public function testGetMultiStampede()
    {
        if (!$this->forkable()) {
            $this->markTestSkipped("Can't test stampede without forking");
        }

        $this->cache->set('key2', 'value2');

        $pid = pcntl_fork();
        if ($pid === -1) {
            // can't fork, ignore this test...
        } elseif ($pid === 0) {
            // request non-existing key: this should make us go in stampede-
            // protection mode if another process/thread requests it again...
            $this->protector->getMulti(array('key', 'key2'));

            // meanwhile, sleep for a small portion of the stampede-protection
            // time - this could be an expensive computation
            usleep(static::SLA / 10 * 1000 + 1);

            // now that we've computed the new value, store it!
            $this->protector->set('key', 'value');

            // exit child process, since I don't want the child to output any
            // test results (would be rather confusing to have output twice)
            exit;
        } else {
            /*
             * Thread execution is in OS scheduler's hands. We don't want to
             * start testing stampede protection until the other thread has done
             * the first request though, so let's wait a bit...
             */
            while ($this->cache->getMulti(array('key', 'key.stampede')) === array()) {
                usleep(10);
            }

            // verify that we WERE able to fetch the value, but had to wait for
            // some time because we were in stampede protection
            $this->assertEquals(
                array('key' => 'value', 'key2' => 'value2'),
                $this->protector->getMulti(array('key', 'key2'))
            );
            $this->assertGreaterThan(0, $this->protector->count);

            pcntl_wait($status);
        }
    }

    /**
     * Forking the parent is process is the only way to accurately test
     * concurrent requests. However, forking comes with its own set of problems,
     * so we may not want to do it in a bunch of cases.
     *
     * @return bool
     */
    protected function forkable()
    {
        if (!function_exists('pcntl_fork')) {
            return false;
        }

        // Now we know $cache will be just fine when forked, but we may be
        // running tests against multiple adapters & not all of them may be fine
        $provider = new AdapterProvider(new static);
        foreach ($provider->getAdapters() as $adapter) {
            if (!$this->forkableAdapter($adapter)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Not all adapters (well, most actually) will handle forking well.
     * E.g. connections will be terminated as soon as child ends, ...
     *
     * @param KeyValueStore $cache
     *
     * @return bool
     */
    protected function forkableAdapter(KeyValueStore $cache)
    {
        // MemoryStore can't share it's "cache" (which is a PHP array) across
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
