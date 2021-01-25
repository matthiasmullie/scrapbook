<?php

namespace MatthiasMullie\Scrapbook\Adapters;

use PDO;

/**
 * PostgreSQL adapter. Basically just a wrapper over \PDO, but in an
 * exchangeable (KeyValueStore) interface.
 *
 * @author Matthias Mullie <scrapbook@mullie.eu>
 * @copyright Copyright (c) 2014, Matthias Mullie. All rights reserved
 * @license LICENSE MIT
 */
class PostgreSQL extends SQL
{
    /**
     * @var bool
     */
    protected $conflictSupport = true;

    /**
     * {@inheritdoc}
     */
    public function flush()
    {
        return false !== $this->client->exec("TRUNCATE TABLE $this->table");
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value, $expire = 0)
    {
        if (!$this->conflictSupport) {
            return parent::set($key, $value, $expire);
        }

        $this->clearExpired();

        $serialized = $this->serialize($value);
        $expiration = $this->expire($expire);

        $statement = $this->client->prepare(
            "INSERT INTO $this->table (k, v, e)
            VALUES (:key, :value, :expire) 
            ON CONFLICT (k) DO UPDATE SET v=EXCLUDED.v, e=EXCLUDED.e"
        );

        $statement->bindParam(':key', $key);
        $statement->bindParam(':value', $serialized, PDO::PARAM_LOB, strlen($serialized));
        $statement->bindParam(':expire', $expiration);
        $statement->execute();

        // ON CONFLICT is not supported in versions < 9.5, in which case we'll
        // have to fall back on add/replace
        if ('42601' === $statement->errorCode()) {
            $this->conflictSupport = false;

            return $this->set($key, $value, $expire);
        }

        return 1 === $statement->rowCount();
    }

    /**
     * {@inheritdoc}
     */
    public function get($key, &$token = null)
    {
        $return = parent::get($key, $token);

        if (null !== $token) {
            // BYTEA data return streams - we actually need the data in
            // serialized format, not some silly stream
            $token = $this->serialize($return);
        }

        return $return;
    }

    /**
     * {@inheritdoc}
     */
    public function getMulti(array $keys, array &$tokens = null)
    {
        $return = parent::getMulti($keys, $tokens);

        foreach ($return as $key => $value) {
            // BYTEA data return streams - we actually need the data in
            // serialized format, not some silly stream
            $tokens[$key] = $this->serialize($value);
        }

        return $return;
    }

    /**
     * {@inheritdoc}
     */
    protected function init()
    {
        $this->client->exec(
            "CREATE TABLE IF NOT EXISTS $this->table (
                k VARCHAR NOT NULL PRIMARY KEY,
                v BYTEA,
                e TIMESTAMP NULL DEFAULT NULL
            )"
        );
        $this->client->exec("CREATE INDEX IF NOT EXISTS e_index ON $this->table (e)");
    }

    /**
     * {@inheritdoc}
     */
    protected function unserialize($value)
    {
        // BYTEA data return streams. Even though it's not how init() will
        // configure the DB by default, it could be used instead!
        if (is_resource($value)) {
            $value = stream_get_contents($value);
        }

        return parent::unserialize($value);
    }
}
