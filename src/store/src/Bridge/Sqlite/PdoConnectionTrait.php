<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Sqlite;

use Symfony\AI\Store\Exception\InvalidArgumentException;

/**
 * @internal
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
trait PdoConnectionTrait
{
    private ?\PDO $connection = null;
    private string $dsn;

    /**
     * @var array{
     *     db_username?: string,
     *     db_password?: string,
     *     db_connection_options?: array<int, mixed>,
     * }
     */
    private array $connectionOptions = [];

    /**
     * @param array{
     *     db_username?: string,
     *     db_password?: string,
     *     db_connection_options?: array<int, mixed>,
     * } $connectionOptions
     */
    private function initializeConnection(#[\SensitiveParameter] \PDO|string $connection, #[\SensitiveParameter] array $connectionOptions): void
    {
        if ($connection instanceof \PDO) {
            if ([] !== $connectionOptions) {
                throw new InvalidArgumentException('The connection options can only be used with a DSN, not with a PDO instance.');
            }

            $this->connection = $connection;

            return;
        }

        $this->dsn = $connection;
        $this->connectionOptions = $connectionOptions;
    }

    private function getConnection(): \PDO
    {
        if (null === $this->connection) {
            $this->connection = new \PDO(
                $this->dsn,
                $this->connectionOptions['db_username'] ?? null,
                $this->connectionOptions['db_password'] ?? null,
                $this->connectionOptions['db_connection_options'] ?? [],
            );
            // the transactions roll back on exceptions, so errors must throw
            $this->connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        }

        return $this->connection;
    }
}
