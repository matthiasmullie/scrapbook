<?php

namespace
{
    require __DIR__.'/../vendor/autoload.php';

    // hostname for these servers will be in env vars
    // if it is not, default to localhost
    if (!getenv('couchbase-host')) {
        putenv('couchbase-host=127.0.0.1');
    }
    if (!getenv('couchbase-port')) {
        putenv('couchbase-port=11210');
    }
    if (!getenv('memcached-host')) {
        putenv('memcached-host=127.0.0.1');
    }
    if (!getenv('memcached-port')) {
        putenv('memcached-port=11211');
    }
    if (!getenv('mysql-host')) {
        putenv('mysql-host=127.0.0.1');
    }
    if (!getenv('mysql-port')) {
        putenv('mysql-port=3306');
    }
    if (!getenv('postgresql-host')) {
        putenv('postgresql-host=127.0.0.1');
    }
    if (!getenv('postgresql-port')) {
        putenv('postgresql-port=5432');
    }
    if (!getenv('redis-host')) {
        putenv('redis-host=127.0.0.1');
    }
    if (!getenv('redis-port')) {
        putenv('redis-port=6379');
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
