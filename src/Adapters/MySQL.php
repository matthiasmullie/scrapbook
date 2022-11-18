<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Adapters;

/**
 * MySQL adapter. Basically just a wrapper over \PDO, but in an exchangeable
 * (KeyValueStore) interface.
 *
 * @author Matthias Mullie <scrapbook@mullie.eu>
 * @copyright Copyright (c) 2014, Matthias Mullie. All rights reserved
 * @license LICENSE MIT
 */
class MySQL extends SQL
{
    public function set(string $key, mixed $value, int $expire = 0): bool
    {
        $value = $this->serialize($value);
        $expire = $this->expire($expire);

        $this->clearExpired();

        $statement = $this->client->prepare(
            "REPLACE INTO $this->table (k, v, e)
            VALUES (:key, :value, :expire)"
        );

        $statement->execute([
            ':key' => $key,
            ':value' => $value,
            ':expire' => $expire,
        ]);

        // 1 = insert; 2 = update
        return $statement->rowCount() === 1 || $statement->rowCount() === 2;
    }

    public function setMulti(array $items, int $expire = 0): array
    {
        if (empty($items)) {
            return [];
        }

        $i = 1;
        $query = [];
        $params = [];
        $expire = $this->expire($expire);

        $this->clearExpired();

        foreach ($items as $key => $value) {
            $value = $this->serialize($value);

            $query[] = "(:key$i, :value$i, :expire$i)";
            $params += [
                ":key$i" => $key,
                ":value$i" => $value,
                ":expire$i" => $expire,
            ];

            ++$i;
        }

        $statement = $this->client->prepare(
            "REPLACE INTO $this->table (k, v, e)
            VALUES " . implode(',', $query)
        );

        $statement->execute($params);

        /*
         * As far as I can tell, there are no conditions under which this can go
         * wrong (if item exists or not, REPLACE INTO will work either way),
         * except for connection problems, in which case all or none will be
         * stored.
         * Can't compare with count($items) because rowCount could be 1 or 2,
         * depending on if REPLACE was an INSERT or UPDATE.
         */
        $success = $statement->rowCount() > 0;

        return array_fill_keys(array_keys($items), $success);
    }

    public function add(string $key, mixed $value, int $expire = 0): bool
    {
        $value = $this->serialize($value);
        $expire = $this->expire($expire);

        $this->clearExpired();

        // MySQL-specific way to ignore insert-on-duplicate errors
        $statement = $this->client->prepare(
            "INSERT IGNORE INTO $this->table (k, v, e)
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
        return $this->client->exec("TRUNCATE TABLE $this->table") !== false;
    }

    protected function init(): void
    {
        $this->client->exec(
            "CREATE TABLE IF NOT EXISTS $this->table (
                k VARBINARY(1000) NOT NULL PRIMARY KEY,
                v LONGBLOB,
                e TIMESTAMP NULL DEFAULT NULL,
                KEY e (e)
            )"
        );
    }
}
