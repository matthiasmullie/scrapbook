<?php

namespace MatthiasMullie\Scrapbook\Tests\PHPUnitCompat;

// PHPUnit 9.x has added return type hints (void) to setUp/tearDown,
// a feature that is BC breaking with older versions
// The only way to work around this is to introduce an additional
// layer with its own setUp/tearDown equivalent functions, without
// the typehint (because older PHP versions can't even parse them)
$reflect = new \ReflectionMethod('PHPUnit\\Framework\\TestCase', 'setUp');
if (method_exists($reflect, 'hasReturnType') && $reflect->hasReturnType()) {
    class CompatTestCase extends ReturnTypehint
    {
    }
} else {
    class CompatTestCase extends NoReturnTypehint
    {
    }
}
