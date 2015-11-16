<?php

namespace MatthiasMullie\Scrapbook\Tests\Scale;

use MatthiasMullie\Scrapbook\Scale\StampedeProtector;

/**
 * The exact same as the real protector, but makes it possible to count the
 * amount of sleeps, so we can properly test if stampede protection actually
 * causes waiting the way we'd expect it.
 */
class StampedeProtectorStub extends StampedeProtector
{
    /**
     * Amount of times sleep() was called.
     *
     * @var int
     */
    public $count = 0;

    /**
     * {@inheritdoc}
     */
    protected function sleep()
    {
        ++$this->count;

        return parent::sleep();
    }
}
