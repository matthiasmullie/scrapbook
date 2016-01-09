<?php

namespace MatthiasMullie\Scrapbook\Tests\Psr6;

use MatthiasMullie\Scrapbook\KeyValueStore;
use MatthiasMullie\Scrapbook\Tests\Adapters\AdapterInterface;
use MatthiasMullie\Scrapbook\Tests\Adapters\AdapterStub;
use MatthiasMullie\Scrapbook\Psr6\Pool;

trait IntegrationTestTrait
{
    /**
     * @var KeyValueStore[]
     */
    protected static $adapters = array();
    /**
     * @return KeyValueStore
     */
    private function getAdapter($name)
    {
        if (isset(static::$adapters[$name])) {
            return static::$adapters[$name];
        }

        try {
            /** @var AdapterInterface $adapter */
            $fqcn = "\\MatthiasMullie\\Scrapbook\\Tests\\Adapters\\{$name}Test";
            $adapter = new $fqcn();

            static::$adapters[$name] = $adapter->get();
        } catch (\Exception $e) {
            static::$adapters[$name] = new AdapterStub($this, $e);
        }

        return static::$adapters[$name];
    }

    /**
     * @param string $name adapter name
     *
     * @return Pool
     */
    protected function getPool($name)
    {
        return new Pool($this->getAdapter($name));
    }
}
