<?php

namespace MatthiasMullie\Scrapbook\Tests\PHPUnitCompat;

use PHPUnit\Framework\TestCase;

class NoReturnTypehint extends TestCase
{
    protected function setUp()
    {
        $backtrace = debug_backtrace();
        $current = array_shift($backtrace);
        foreach ($backtrace as $call) {
            if ($call['class'] === $current['class'] && $call['function'] === $current['function']) {
                // prevent recursion
                return;
            }
        }
        $this->compatSetUp();
    }

    protected function tearDown()
    {
        $backtrace = debug_backtrace();
        $current = array_shift($backtrace);
        foreach ($backtrace as $call) {
            if ($call['class'] === $current['class'] && $call['function'] === $current['function']) {
                // prevent recursion
                return;
            }
        }
        $this->compatTearDown();
    }

    protected function compatSetUp()
    {
        parent::setUp();
    }

    protected function compatTearDown()
    {
        parent::tearDown();
    }
}
