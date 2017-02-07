<?php

namespace
{
    require __DIR__.'/../vendor/autoload.php';
}

namespace Cache\IntegrationTests
{
    // PSR-6 integration tests
    if (!class_exists('Cache\IntegrationTests\CachePoolTest')) {
        class CachePoolTest extends \PHPUnit_Framework_TestCase
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

    // PSR-16 integration tests
    if (!class_exists('Cache\IntegrationTests\SimpleCacheTest')) {
        class SimpleCacheTest extends \PHPUnit_Framework_TestCase
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
