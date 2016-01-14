<?php

namespace MatthiasMullie\Scrapbook\Psr6\Taggable;

use Cache\Taggable\TaggablePoolInterface;
use Cache\Taggable\TaggablePoolTrait;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * PSR-6 & TaggablePoolInterface compliant implementation that wraps over every
 * PSR-6 implementation.
 *
 * @author Matthias Mullie <scrapbook@mullie.eu>
 * @copyright Copyright (c) 2014, Matthias Mullie. All rights reserved.
 * @license MIT License
 */
class Pool implements CacheItemPoolInterface, TaggablePoolInterface
{
    use TaggablePoolTrait;

    /**
     * List of invalid (or reserved) key characters.
     *
     * @var string
     */
    const KEY_INVALID_CHARACTERS = '{}()/\@:';

    /**
     * @var CacheItemPoolInterface
     */
    protected $pool;

    /**
     * @param CacheItemPoolInterface $pool
     */
    public function __construct(CacheItemPoolInterface $pool)
    {
        $this->pool = $pool;
    }

    /**
     * {@inheritdoc}
     *
     * @return Item
     */
    public function getItem($key, array $tags = [])
    {
        $taggedKey = $this->generateCacheKey($key, $tags);

        return $this->getItemWithoutGenerateCacheKey($taggedKey);
    }

    /**
     * {@inheritdoc}
     *
     * @return Item[]
     */
    public function getItems(array $keys = [], array $tags = [])
    {
        $taggedKeys = [];
        foreach ($keys as $key) {
            $taggedKeys[] = $this->generateCacheKey($key, $tags);
        }

        return $this->getItemsWithoutGenerateCacheKey($taggedKeys);
    }

    /**
     * {@inheritdoc}
     */
    public function hasItem($key, array $tags = [])
    {
        return $this->getItem($key, $tags)->isHit();
    }

    /**
     * {@inheritdoc}
     */
    public function clear(array $tags = [])
    {
        if (!empty($tags)) {
            foreach ($tags as $tag) {
                $this->flushTag($tag);
            }

            return true;
        }

        return $this->pool->clear();
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItem($key, array $tags = [])
    {
        $taggedKey = $this->generateCacheKey($key, $tags);

        return $this->pool->deleteItem($taggedKey);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItems(array $keys, array $tags = [])
    {
        $taggedKeys = [];
        foreach ($keys as $key) {
            $taggedKeys[] = $this->generateCacheKey($key, $tags);
        }

        return $this->pool->deleteItems($taggedKeys);
    }

    /**
     * {@inheritdoc}
     */
    public function save(CacheItemInterface $item)
    {
        if (!$item instanceof Item) {
            throw new InvalidArgumentException(
                'MatthiasMullie\Scrapbook\Taggable\Pool can only save
                MatthiasMullie\Scrapbook\Taggable\Item objects'
            );
        }

        // unwrap Taggable\Item & save it to original Pool
        return $this->pool->save($item->getOriginal());
    }

    /**
     * {@inheritdoc}
     */
    public function saveDeferred(CacheItemInterface $item)
    {
        if (!$item instanceof Item) {
            throw new InvalidArgumentException(
                'MatthiasMullie\Scrapbook\Taggable\Pool can only save
                MatthiasMullie\Scrapbook\Taggable\Item objects'
            );
        }

        // unwrap Taggable\Item & save it to original Pool
        return $this->pool->saveDeferred($item->getOriginal());
    }

    /**
     * {@inheritdoc}
     */
    public function commit()
    {
        return $this->pool->commit();
    }

    /**
     * {@inheritdoc}
     */
    protected function getItemWithoutGenerateCacheKey($key)
    {
        $item = $this->pool->getItem($key);

        // wrap the pool's result item into a Taggable/Item
        return new Item($item);
    }

    /**
     * This does exactly what getItemWithoutGenerateCacheKey does, but by multi-
     * fetching multiple items.
     *
     * @param array $keys
     *
     * @return array
     */
    protected function getItemsWithoutGenerateCacheKey(array $keys)
    {
        /** @var CacheItemInterface[] $items */
        $items = $this->pool->getItems($keys);
        $taggableItems = [];
        foreach ($items as $item) {
            // wrap the pool's result item into a Taggable/Item
            $taggableItem = new Item($item);
            $taggableItems[$taggableItem->getKey()] = $taggableItem;
        }

        return $taggableItems;
    }

    /**
     * {@inheritdoc}
     */
    protected function validateTagName($name)
    {
        if (!is_string($name)) {
            throw new InvalidArgumentException(
                'Invalid key: '.var_export($name, true).'. Key should be a string.'
            );
        }

        // valid key according to PSR-6 rules
        $invalid = preg_quote(static::KEY_INVALID_CHARACTERS, '/');
        if (preg_match('/['.$invalid.']/', $name)) {
            throw new InvalidArgumentException(
                'Invalid tag name: '.$name.'. Contains (a) character(s) '.
                'reserved for future extension: '.static::KEY_INVALID_CHARACTERS
            );
        }
    }
}
