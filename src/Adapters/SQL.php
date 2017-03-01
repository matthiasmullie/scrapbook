<?php

namespace MatthiasMullie\Scrapbook\Adapters;

use PDO;
use MatthiasMullie\Scrapbook\KeyValueStore;
use MatthiasMullie\Scrapbook\Adapters\Collections\SQL as Collection;

/**
 * SQL adapter. Basically just a wrapper over \PDO, but in an exchangeable
 * (KeyValueStore) interface.
 *
 * This abstract class should be a "fits all DB engines" normalization. It's up
 * to extending classes to optimize for that specific engine.
 *
 * @author Matthias Mullie <scrapbook@mullie.eu>
 * @copyright Copyright (c) 2014, Matthias Mullie. All rights reserved
 * @license LICENSE MIT
 */
abstract class SQL implements KeyValueStore
{
    /**
     * @var PDO
     */
    protected $client;

    /**
     * @var string
     */
    protected $table;

    /**
     * Create the database/indices if it does not already exist.
     */
    abstract protected function init();

    /**
     * @param PDO    $client
     * @param string $table
     */
    public function __construct(PDO $client, $table = 'cache')
    {
        $this->client = $client;
        $this->table = $table;

        // don't throw exceptions - it's ok to fail, as long as the return value
        // reflects that!
        $this->client->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);

        // make sure the database exists (or just "fail" silently)
        $this->init();

        // now's a great time to clean up all expired items
        $this->clearExpired();
    }

    /**
     * {@inheritdoc}
     */
    public function get($key, &$token = null)
    {
        $statement = $this->client->prepare(
            "SELECT v
            FROM $this->table
            WHERE k = :key AND (e IS NULL OR e > :expire)"
        );
        $statement->execute(array(
            ':key' => $key,
            ':expire' => date('Y-m-d H:i:s'), // right now!
        ));

        $result = $statement->fetch(PDO::FETCH_ASSOC);

        if (!isset($result['v'])) {
            $token = null;

            return false;
        }

        $token = $result['v'];

        return $this->unserialize($result['v']);
    }

    /**
     * {@inheritdoc}
     */
    public function getMulti(array $keys, array &$tokens = null)
    {
        $tokens = array();
        if (empty($keys)) {
            return array();
        }

        // escape input, can't bind multiple params for IN()
        $quoted = array();
        foreach ($keys as $key) {
            $quoted[] = $this->client->quote($key);
        }

        $statement = $this->client->prepare(
            "SELECT k, v
            FROM $this->table
            WHERE
                k IN (".implode(',', $quoted).') AND
                (e IS NULL OR e > :expire)'
        );
        $statement->execute(array(':expire' => date('Y-m-d H:i:s')));
        $values = $statement->fetchAll(PDO::FETCH_ASSOC);

        $result = array();
        $tokens = array();
        foreach ($values as $value) {
            $tokens[$value['k']] = $value['v'];
            $result[$value['k']] = $this->unserialize($value['v']);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value, $expire = 0)
    {
        // PostgreSQL doesn't have a decent UPSERT (like REPLACE or even INSERT
        // ... ON DUPLICATE KEY UPDATE ...); here's a "works for all" downgrade
        $success = $this->add($key, $value, $expire);
        if ($success) {
            return true;
        }

        $success = $this->replace($key, $value, $expire);
        if ($success) {
            return true;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function setMulti(array $items, $expire = 0)
    {
        $success = array();

        // PostgreSQL's lack of a decent UPSERT is even worse for multiple
        // values - we can only do them one at a time...
        foreach ($items as $key => $value) {
            $success[$key] = $this->set($key, $value, $expire);
        }

        return $success;
    }

    /**
     * {@inheritdoc}
     */
    public function delete($key)
    {
        $statement = $this->client->prepare(
            "DELETE FROM $this->table
            WHERE k = :key"
        );

        $statement->execute(array(':key' => $key));

        return $statement->rowCount() === 1;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMulti(array $keys)
    {
        if (empty($keys)) {
            return array();
        }

        // we'll need these to figure out which could not be deleted...
        $items = $this->getMulti($keys);

        // escape input, can't bind multiple params for IN()
        $quoted = array();
        foreach ($keys as $key) {
            $quoted[] = $this->client->quote($key);
        }

        $statement = $this->client->query(
            "DELETE FROM $this->table
            WHERE k IN (".implode(',', $quoted).')'
        );

        /*
         * In case of connection problems, we may not have been able to delete
         * any. Otherwise, we'll use the getMulti() results to figure out which
         * couldn't be deleted because they didn't exist at that time.
         */
        $success = $statement->rowCount() !== 0;
        $success = array_fill_keys($keys, $success);
        foreach ($keys as $key) {
            if (!array_key_exists($key, $items)) {
                $success[$key] = false;
            }
        }

        return $success;
    }

    /**
     * {@inheritdoc}
     */
    public function add($key, $value, $expire = 0)
    {
        $value = $this->serialize($value);
        $expire = $this->expire($expire);

        $this->clearExpired();

        $statement = $this->client->prepare(
            "INSERT INTO $this->table (k, v, e)
            VALUES (:key, :value, :expire)"
        );

        $statement->execute(array(
            ':key' => $key,
            ':value' => $value,
            ':expire' => $expire,
        ));

        return $statement->rowCount() === 1;
    }

    /**
     * {@inheritdoc}
     */
    public function replace($key, $value, $expire = 0)
    {
        $value = $this->serialize($value);
        $expire = $this->expire($expire);

        $this->clearExpired();

        $statement = $this->client->prepare(
            "UPDATE $this->table
            SET v = :value, e = :expire
            WHERE k = :key"
        );

        $statement->execute(array(
            ':key' => $key,
            ':value' => $value,
            ':expire' => $expire,
        ));

        if ($statement->rowCount() === 1) {
            return true;
        }

        // if the value we've just replaced was the same as the replacement, as
        // well as the same expiration time, rowCount will have been 0, but the
        // operation was still a success
        $statement = $this->client->prepare(
            "SELECT e
            FROM $this->table
            WHERE k = :key AND v = :value"
        );
        $statement->execute(array(
            ':key' => $key,
            ':value' => $value,
        ));

        return $statement->fetchColumn(0) === $expire;
    }

    /**
     * {@inheritdoc}
     */
    public function cas($token, $key, $value, $expire = 0)
    {
        $value = $this->serialize($value);
        $expire = $this->expire($expire);

        $this->clearExpired();

        $statement = $this->client->prepare(
            "UPDATE $this->table
            SET v = :value, e = :expire
            WHERE k = :key AND v = :token"
        );

        $statement->execute(array(
            ':key' => $key,
            ':value' => $value,
            ':expire' => $expire,
            ':token' => $token,
        ));

        if ($statement->rowCount() === 1) {
            return true;
        }

        // if the value we've just cas'ed was the same as the replacement, as
        // well as the same expiration time, rowCount will have been 0, but the
        // operation was still a success
        $statement = $this->client->prepare(
            "SELECT e
            FROM $this->table
            WHERE k = :key AND v = :value AND v = :token"
        );
        $statement->execute(array(
            ':key' => $key,
            ':value' => $value,
            ':token' => $token,
        ));

        return $statement->fetchColumn(0) === $expire;
    }

    /**
     * {@inheritdoc}
     */
    public function increment($key, $offset = 1, $initial = 0, $expire = 0)
    {
        if ($offset <= 0 || $initial < 0) {
            return false;
        }

        return $this->doIncrement($key, $offset, $initial, $expire);
    }

    /**
     * {@inheritdoc}
     */
    public function decrement($key, $offset = 1, $initial = 0, $expire = 0)
    {
        if ($offset <= 0 || $initial < 0) {
            return false;
        }

        return $this->doIncrement($key, -$offset, $initial, $expire);
    }

    /**
     * {@inheritdoc}
     */
    public function touch($key, $expire)
    {
        $expire = $this->expire($expire);

        $this->clearExpired();

        $statement = $this->client->prepare(
            "UPDATE $this->table
            SET e = :expire
            WHERE k = :key"
        );

        $statement->execute(array(
            ':key' => $key,
            ':expire' => $expire,
        ));

        return $statement->rowCount() === 1;
    }

    /**
     * {@inheritdoc}
     */
    public function flush()
    {
        // TRUNCATE doesn't work on SQLite - DELETE works for all
        return $this->client->exec("DELETE FROM $this->table") !== false;
    }

    /**
     * {@inheritdoc}
     */
    public function getCollection($name)
    {
        return new Collection($this, $this->client, $this->table, $name);
    }

    /**
     * Shared between increment/decrement: both have mostly the same logic
     * (decrement just increments a negative value), but need their validation
     * & use of non-ttl native methods split up.
     *
     * @param string $key
     * @param int    $offset
     * @param int    $initial
     * @param int    $expire
     *
     * @return int|bool
     */
    protected function doIncrement($key, $offset, $initial, $expire)
    {
        /*
         * I used to have all this logic in a huge & ugly query, but getting
         * that right on multiple SQL engines proved challenging (SQLite doesn't
         * do INSERT ... ON DUPLICATE KEY UPDATE ..., for example)
         * I'll just stuff it in a transaction & leverage existing methods.
         */
        $this->client->beginTransaction();
        $this->clearExpired();

        $value = $this->get($key);
        if ($value === false) {
            $return = $this->add($key, $initial, $expire);

            if ($return) {
                $this->client->commit();

                return $initial;
            }
        } elseif (is_numeric($value)) {
            $value += $offset;
            // < 0 is never possible
            $value = max(0, $value);
            $return = $this->replace($key, $value, $expire);

            if ($return) {
                $this->client->commit();

                return (int) $value;
            }
        }

        $this->client->rollBack();

        return false;
    }

    /**
     * Expired entries shouldn't keep filling up the database. Additionally,
     * we will want to remove those in order to properly rely on INSERT (for
     * add) and UPDATE (for replace), which assume a column exists or not, not
     * taking the expiration status into consideration.
     * An expired column should simply not exist.
     */
    protected function clearExpired()
    {
        $statement = $this->client->prepare(
            "DELETE FROM $this->table
            WHERE e < :expire"
        );

        $statement->execute(array(':expire' => date('Y-m-d H:i:s')));
    }

    /**
     * Transforms expiration times into TIMESTAMP (Y-m-d H:i:s) format, which DB
     * will understand and be able to compare with other dates.
     *
     * @param int $expire
     *
     * @return null|string
     */
    protected function expire($expire)
    {
        if ($expire === 0) {
            return;
        }

        // relative time in seconds, <30 days
        if ($expire < 30 * 24 * 60 * 60) {
            $expire += time();
        }

        return date('Y-m-d H:i:s', $expire);
    }

    /**
     * I originally didn't want to serialize numeric values because I planned
     * on incrementing them in the DB, but revisited that idea.
     * However, not serializing numbers still causes some small DB storage gains
     * and it's safe (serialized data can never be confused for an int).
     *
     * @param mixed $value
     *
     * @return string|int
     */
    protected function serialize($value)
    {
        return is_int($value) || is_float($value) ? $value : serialize($value);
    }

    /**
     * Numbers aren't serialized for storage size purposes.
     *
     * @param mixed $value
     *
     * @return mixed|int|float
     */
    protected function unserialize($value)
    {
        if (is_numeric($value)) {
            $int = (int) $value;
            if ((string) $int === $value) {
                return $int;
            }

            $float = (float) $value;
            if ((string) $float === $value) {
                return $float;
            }

            return $value;
        }

        return unserialize($value);
    }
}
