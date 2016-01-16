<?php

namespace MatthiasMullie\Scrapbook\Adapters;

/**
 * PostgreSQL adapter. Basically just a wrapper over \PDO, but in an
 * exchangeable (KeyValueStore) interface.
 *
 * @author Matthias Mullie <scrapbook@mullie.eu>
 * @copyright Copyright (c) 2014, Matthias Mullie. All rights reserved.
 * @license LICENSE MIT
 */
class PostgreSQL extends SQL
{
    /**
     * {@inheritdoc}
     */
    public function flush()
    {
        return $this->client->exec("TRUNCATE TABLE $this->table") !== false;
    }

    /**
     * {@inheritdoc}
     */
    public function get($key, &$token = null)
    {
        $return = parent::get($key, $token);

        if ($token !== null) {
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
                v TEXT,
                e TIMESTAMP NULL DEFAULT NULL
            )"
        );
        $this->client->exec("CREATE INDEX ON $this->table (e)");
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
