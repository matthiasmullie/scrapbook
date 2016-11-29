<?php

namespace MatthiasMullie\Scrapbook\Psr16;

use MatthiasMullie\Scrapbook\Exception\Exception;

class InvalidArgumentException extends Exception implements \Psr\SimpleCache\InvalidArgumentException
{
}
