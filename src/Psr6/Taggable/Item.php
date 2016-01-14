<?php

namespace MatthiasMullie\Scrapbook\Psr6\Taggable;

use Cache\Taggable\TaggableItemInterface;
use Cache\Taggable\TaggableItemTrait;
use Psr\Cache\CacheItemInterface;

/**
 * Representation of a cache item, both existing & non-existing (to be created).
 *
 * @author Matthias Mullie <scrapbook@mullie.eu>
 * @copyright Copyright (c) 2014, Matthias Mullie. All rights reserved.
 * @license MIT License
 */
class Item implements CacheItemInterface, TaggableItemInterface
{
    use TaggableItemTrait;

    /**
     * @var CacheItemInterface
     */
    protected $item;

    /**
     * @param CacheItemInterface $item
     */
    public function __construct(CacheItemInterface $item)
    {
        $this->item = $item;
        $this->taggedKey = $item->getKey();
    }

    /**
     * {@inheritdoc}
     */
    public function getKey()
    {
        return $this->getKeyFromTaggedKey($this->taggedKey);
    }

    /**
     * {@inheritdoc}
     */
    public function get()
    {
        return $this->item->get();
    }

    /**
     * {@inheritdoc}
     */
    public function set($value)
    {
        $this->item->set($value);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function isHit()
    {
        return $this->item->isHit();
    }

    /**
     * {@inheritdoc}
     */
    public function expiresAt($expiration)
    {
        $this->item->expiresAt($expiration);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function expiresAfter($time)
    {
        $this->item->expiresAfter($time);

        return $this;
    }

    /**
     * Returns the original item, which the original Pool can recognize & deal
     * with.
     *
     * @return CacheItemInterface
     */
    public function getOriginal()
    {
        return $this->item;
    }
}
