<?php

namespace
{
    require __DIR__.'/../vendor/autoload.php';

    // hostname for these servers will be in env vars
    // if it is not, default to localhost
    if (!getenv('host-couchbase')) {
        putenv('host-couchbase=127.0.0.1');
    }
    if (!getenv('host-memcached')) {
        putenv('host-memcached=127.0.0.1');
    }
    if (!getenv('host-mysql')) {
        putenv('host-mysql=127.0.0.1');
    }
    if (!getenv('host-postgresql')) {
        putenv('host-postgresql=127.0.0.1');
    }
    if (!getenv('host-redis')) {
        putenv('host-redis=127.0.0.1');
    }

    // compatibility for when cache/integration-tests are run with PHPUnit>=6.0
    if (!class_exists('PHPUnit_Framework_TestCase')) {
        abstract class PHPUnit_Framework_TestCase extends \PHPUnit\Framework\TestCase
        {
        }
    }
}

namespace PHPUnit\Framework
{
    // compatibility for when these tests are run with PHPUnit<6.0 (which we
    // still do because PHPUnit=6.0 stopped supporting a lot of PHP versions)
    if (!class_exists('PHPUnit\Framework\TestCase')) {
        abstract class TestCase extends \PHPUnit_Framework_TestCase
        {
        }
    }
    if (!class_exists('PHPUnit\Framework\TestSuite')) {
        class TestSuite extends \PHPUnit_Framework_TestSuite
        {
        }
    }
}

namespace Cache\IntegrationTests
{
    // PSR-6 integration tests, where cache/integration-tests files may not
    // exist because of PHP version dependency
    if (!class_exists('Cache\IntegrationTests\CachePoolTest')) {
        class CachePoolTest extends \PHPUnit\Framework\TestCase
        {
            public function testIncomplete()
            {
                $this->markTestIncomplete(
                    'Missing dependencies. Please run: '.
                    'composer require --dev cache/integration-tests:dev-master'
                );
            }
        }
    }

    // PSR-16 integration tests, where cache/integration-tests files may not
    // exist because of PHP version dependency
    if (!class_exists('Cache\IntegrationTests\SimpleCacheTest')) {
        class SimpleCacheTest extends \PHPUnit\Framework\TestCase
        {
            public function testIncomplete()
            {
                $this->markTestIncomplete(
                    'Missing dependencies. Please run: '.
                    'composer require --dev cache/integration-tests:dev-master'
                );
            }
        }
    }
}
