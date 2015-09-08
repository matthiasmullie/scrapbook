<?php

namespace Psr\Cache;

/**
 * CacheItemInterface defines an interface for interacting with objects inside a cache.
 */
interface CacheItemInterface
{
    /**
     * Returns the key for the current cache item.
     *
     * The key is loaded by the Implementing Library, but should be available to
     * the higher level callers when needed.
     *
     * @return string
     *                The key string for this cache item.
     */
    public function getKey();

    /**
     * Retrieves the value of the item from the cache associated with this object's key.
     *
     * The value returned must be identical to the value original stored by set().
     *
     * if isHit() returns false, this method MUST return null. Note that null
     * is a legitimate cached value, so the isHit() method SHOULD be used to
     * differentiate between "null value was found" and "no value was found."
     *
     * @return mixed
     *               The value corresponding to this cache item's key, or null if not found.
     */
    public function get();

    /**
     * Sets the value represented by this cache item.
     *
     * The $value argument may be any item that can be serialized by PHP,
     * although the method of serialization is left up to the Implementing
     * Library.
     *
     * Implementing Libraries MAY provide a default TTL if one is not specified.
     * If no TTL is specified and no default TTL has been set, the TTL MUST
     * be set to the maximum possible duration of the underlying storage
     * mechanism, or permanent if possible.
     *
     * @param mixed $value
     *                     The serializable value to be stored.
     *
     * @return static
     *                The invoked object.
     */
    public function set($value);

    /**
     * Confirms if the cache item lookup resulted in a cache hit.
     *
     * Note: This method MUST NOT have a race condition between calling isHit()
     * and calling get().
     *
     * @return bool
     *              True if the request resulted in a cache hit. False otherwise.
     */
    public function isHit();

    /**
     * Confirms if the cache item exists in the cache.
     *
     * Note: This method MAY avoid retrieving the cached value for performance
     * reasons, which could result in a race condition between exists() and get().
     * To avoid that potential race condition use isHit() instead.
     *
     * @return bool
     *              True if item exists in the cache, false otherwise.
     */
    public function exists();

    /**
     * Sets the expiration time for this cache item.
     *
     * @param \DateTimeInterface $expiration
     *                                       The point in time after which the item MUST be considered expired.
     *                                       If null is passed explicitly, a default value MAY be used. If none is set,
     *                                       the value should be stored permanently or for as long as the
     *                                       implementation allows.
     *
     * @return static
     *                The called object.
     */
    public function expiresAt($expiration);

    /**
     * Sets the expiration time for this cache item.
     *
     * @param int|\DateInterval $time
     *                                The period of time from the present after which the item MUST be considered
     *                                expired. An integer parameter is understood to be the time in seconds until
     *                                expiration.
     *
     * @return static
     *                The called object.
     */
    public function expiresAfter($time);

    /**
     * Returns the expiration time of a not-yet-expired cache item.
     *
     * If this cache item is a Cache Miss, this method MAY return the time at
     * which the item expired or the current time if that is not available.
     *
     * @return \DateTime
     *                   The timestamp at which this cache item will expire.
     */
    public function getExpiration();
}
