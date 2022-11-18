<?php

declare(strict_types=1);

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
    public function setMulti(array $items, int $expire = 0): array
    {
        if (empty($items)) {
            return [];
        }

        $expire = $this->expire($expire);

        $this->clearExpired();

        // SQLite < 3.7.11 doesn't support multi-insert/replace!

        $statement = $this->client->prepare(
            "REPLACE INTO $this->table (k, v, e)
            VALUES (:key, :value, :expire)"
        );

        $success = [];
        foreach ($items as $key => $value) {
            $value = $this->serialize($value);

            $statement->execute([
                ':key' => $key,
                ':value' => $value,
                ':expire' => $expire,
            ]);

            $success[$key] = (bool) $statement->rowCount();
        }

        return $success;
    }

    public function add(string $key, mixed $value, int $expire = 0): bool
    {
        $value = $this->serialize($value);
        $expire = $this->expire($expire);

        $this->clearExpired();

        // SQLite-specific way to ignore insert-on-duplicate errors
        $statement = $this->client->prepare(
            "INSERT OR IGNORE INTO $this->table (k, v, e)
            VALUES (:key, :value, :expire)"
        );

        $statement->execute([
            ':key' => $key,
            ':value' => $value,
            ':expire' => $expire,
        ]);

        return $statement->rowCount() === 1;
    }

    public function flush(): bool
    {
        return $this->client->exec("DELETE FROM $this->table") !== false;
    }

    protected function init(): void
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
