<?php

namespace MatthiasMullie\Scrapbook\Adapters;

/**
 * SQLite adapter. Basically just a wrapper over \PDO, but in an exchangeable
 * (KeyValueStore) interface.
 *
 * @author Matthias Mullie <scrapbook@mullie.eu>
 * @copyright Copyright (c) 2014, Matthias Mullie. All rights reserved
 * @license LICENSE MIT
 */
class SQLite extends MySQL
{
    /**
     * {@inheritdoc}
     */
    public function setMulti(array $items, $expire = 0)
    {
        if (empty($items)) {
            return array();
        }

        $expire = $this->expire($expire);

        $this->clearExpired();

        // SQLite < 3.7.11 doesn't support multi-insert/replace!

        $statement = $this->client->prepare(
            "REPLACE INTO $this->table (k, v, e)
            VALUES (:key, :value, :expire)"
        );

        $success = array();
        foreach ($items as $key => $value) {
            $value = $this->serialize($value);

            $statement->execute(array(
                ':key' => $key,
                ':value' => $value,
                ':expire' => $expire,
            ));

            $success[$key] = (bool) $statement->rowCount();
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

        // SQLite-specific way to ignore insert-on-duplicate errors
        $statement = $this->client->prepare(
            "INSERT OR IGNORE INTO $this->table (k, v, e)
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
    public function flush()
    {
        return $this->client->exec("DELETE FROM $this->table") !== false;
    }

    /**
     * {@inheritdoc}
     */
    protected function init()
    {
        $this->client->exec(
            "CREATE TABLE IF NOT EXISTS $this->table (
                k VARCHAR(255) NOT NULL PRIMARY KEY,
                v BLOB,
                e TIMESTAMP NULL DEFAULT NULL,
                KEY e
            )"
        );
    }
}
