<?php

namespace MatthiasMullie\Scrapbook\Tests\Scale;

use MatthiasMullie\Scrapbook\Adapters\Apc;
use MatthiasMullie\Scrapbook\Adapters\Couchbase;
use MatthiasMullie\Scrapbook\Adapters\Memcached;
use MatthiasMullie\Scrapbook\Adapters\MemoryStore;
use MatthiasMullie\Scrapbook\Adapters\Redis;
use MatthiasMullie\Scrapbook\Adapters\SQL;
use MatthiasMullie\Scrapbook\KeyValueStore;
use MatthiasMullie\Scrapbook\Tests\AdapterProviderTestCase;

class StampedeProtectorTest extends AdapterProviderTestCase
{
    /**
     * Time (in milliseconds) to protect against stampede.
     *
     * @var int
     */
    const SLA = 100;

    public function adapterProvider()
    {
        parent::adapterProvider();

        // can't access "static" inside closure in PHP < 5.4
        $sla = static::SLA;
        return array_map(function (KeyValueStore $adapter) use ($sla) {
            return array(new StampedeProtectorStub($adapter, $sla), $adapter);
        }, $this->adapters);
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testGetExisting(StampedeProtectorStub $protector, KeyValueStore $cache)
    {
        $cache->set('key', 'value');

        /*
         * Verify that we WERE able to fetch the value, DIDN'T wait (less than
         * a tenth of the SLA, the smallest "break" that will be waited if in
         * stampede protection) & DIDN'T create a tmp "stampede" indicator file.
         */
        $this->assertEquals('value', $protector->get('key'));
        $this->assertEquals(0, $protector->count);
        $this->assertEquals(false, $cache->get('key.stampede'));
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testGetNonExisting(StampedeProtectorStub $protector, KeyValueStore $cache)
    {
        /*
         * Verify that we WEREN'T able to fetch the value, DIDN'T wait & DID
         * create a tmp "stampede" indicator file.
         */
        $this->assertEquals(false, $protector->get('key'));
        $this->assertEquals(0, $protector->count);
        $this->assertEquals('', $cache->get('key.stampede'));
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testGetStampede(StampedeProtectorStub $protector, KeyValueStore $cache)
    {
        if (!$this->forkable($cache)) {
            $this->markTestSkipped("Can't test stampede without forking");
        }

        $pid = pcntl_fork();
        if ($pid === -1) {
            // can't fork, ignore this test...
        } elseif ($pid === 0) {
            // request non-existing key: this should make us go in stampede-
            // protection mode if another process/thread requests it again...
            $protector->get('key');

            // meanwhile, sleep for a small portion of the stampede-protection
            // time - this could be an expensive computation
            usleep(static::SLA / 10 * 1000 + 1);

            // now that we've computed the new value, store it!
            $protector->set('key', 'value');

            // exit child process, since I don't want the child to output any
            // test results (would be rather confusing to have output twice)
            exit;
        } else {
            /*
             * Thread execution is in OS scheduler's hands. We don't want to
             * start testing stampede protection until the other thread has done
             * the first request though, so let's wait a bit...
             */
            while ($cache->getMulti(array('key', 'key.stampede')) === array()) {
                usleep(10);
            }

            // verify that we WERE able to fetch the value, but had to wait for
            // some time because we were in stampede protection
            $this->assertEquals('value', $protector->get('key'));
            $this->assertGreaterThan(0, $protector->count);
        }
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testGetMultiExisting(StampedeProtectorStub $protector, KeyValueStore $cache)
    {
        $cache->setMulti(array('key' => 'value', 'key2' => 'value2'));

        /*
         * Verify that we WERE able to fetch the values, DIDN'T wait & DIDN'T
         * create a tmp "stampede" indicator file.
         */
        $this->assertEquals(
            array('key' => 'value', 'key2' => 'value2'),
            $protector->getMulti(array('key', 'key2'))
        );
        $this->assertEquals(0, $protector->count);
        $this->assertEquals(
            array(),
            $cache->getMulti(array('key.stampede', 'key2.stampede'))
        );
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testGetMultiNonExisting(StampedeProtectorStub $protector, KeyValueStore $cache)
    {
        /*
         * Verify that we WEREN'T able to fetch the values, DIDN'T wait & DID
         * create a tmp "stampede" indicator file.
         */
        $this->assertEquals(
            array(),
            $protector->getMulti(array('key', 'key2'))
        );
        $this->assertEquals(0, $protector->count);
        $this->assertEquals(
            array('key.stampede' => '', 'key2.stampede' => ''),
            $cache->getMulti(array('key.stampede', 'key2.stampede'))
        );
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testGetMultiExistingAndNonExisting(StampedeProtectorStub $protector, KeyValueStore $cache)
    {
        $cache->set('key', 'value');

        /*
         * Verify that we WERE & WEREN'T able to fetch the values, DIDN'T wait &
         * DID create a tmp "stampede" indicator file for the missing value.
         */
        $this->assertEquals(
            array('key' => 'value'),
            $protector->getMulti(array('key', 'key2'))
        );
        $this->assertEquals(0, $protector->count);
        $this->assertEquals(
            array('key2.stampede' => ''),
            $cache->getMulti(array('key.stampede', 'key2.stampede'))
        );
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testGetMultiStampede(StampedeProtectorStub $protector, KeyValueStore $cache)
    {
        if (!$this->forkable($cache)) {
            $this->markTestSkipped("Can't test stampede without forking");
        }

        $cache->set('key2', 'value2');

        $pid = pcntl_fork();
        if ($pid === -1) {
            // can't fork, ignore this test...
        } elseif ($pid === 0) {
            // request non-existing key: this should make us go in stampede-
            // protection mode if another process/thread requests it again...
            $protector->getMulti(array('key', 'key2'));

            // meanwhile, sleep for a small portion of the stampede-protection
            // time - this could be an expensive computation
            usleep(static::SLA / 10 * 1000 + 1);

            // now that we've computed the new value, store it!
            $protector->set('key', 'value');

            // exit child process, since I don't want the child to output any
            // test results (would be rather confusing to have output twice)
            exit;
        } else {
            /*
             * Thread execution is in OS scheduler's hands. We don't want to
             * start testing stampede protection until the other thread has done
             * the first request though, so let's wait a bit...
             */
            while ($cache->getMulti(array('key', 'key.stampede')) === array()) {
                usleep(10);
            }

            // verify that we WERE able to fetch the value, but had to wait for
            // some time because we were in stampede protection
            $this->assertEquals(
                array('key' => 'value', 'key2' => 'value2'),
                $protector->getMulti(array('key', 'key2'))
            );
            $this->assertGreaterThan(0, $protector->count);
        }
    }

    /**
     * Forking the parent is process is the only way to accurately test
     * concurrent requests. However, forking comes with its own set of problems,
     * so we may not want to do it in a bunch of cases.
     *
     * @param KeyValueStore $cache
     *
     * @return bool
     */
    protected function forkable(KeyValueStore $cache)
    {
        if (!function_exists('pcntl_fork')) {
            return false;
        }

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
