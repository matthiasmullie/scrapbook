<?php

namespace MatthiasMullie\Scrapbook\Psr6;

use MatthiasMullie\Scrapbook\Exception\Exception;

class InvalidArgumentException extends Exception implements \Psr\Cache\InvalidArgumentException
{
}
