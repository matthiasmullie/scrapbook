<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Psr16;

use MatthiasMullie\Scrapbook\Exception\Exception;

class InvalidArgumentException extends Exception implements \Psr\SimpleCache\InvalidArgumentException
{
}
