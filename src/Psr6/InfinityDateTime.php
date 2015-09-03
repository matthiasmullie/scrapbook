<?php

namespace MatthiasMullie\Scrapbook\Psr6;

use DateTime;

/**
 * Stub class to represent infinity, while still keeping with the PSR-6
 * requirement of representing TTL with DateTime objects (with which it's not
 * possible to represent an infinite time, for permanent keys).
 */
class InfinityDateTime extends DateTime
{
}
