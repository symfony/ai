<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Symfony\Messenger;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Symfony\AI\Mate\Bridge\Symfony\Exception\FailureTransportNotReadableException;

/**
 * Reads the rows of a Doctrine Messenger transport (`doctrine://<connection>`) with plain SELECTs.
 *
 * Read-only by construction: no locking read (no FOR UPDATE, unlike the transport's own get()),
 * no UPDATE of delivered_at, no DELETE, no schema setup (a missing table is reported, never
 * created); SQLite files are opened read-only and never created.
 *
 * @internal
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class DoctrineTransportReader
{
    private const DEFAULT_OPTIONS = ['table_name' => 'messenger_messages', 'queue_name' => 'default'];

    private const CONNECTION_KEYS = ['driver', 'host', 'port', 'user', 'password', 'dbname', 'path', 'memory', 'charset', 'unix_socket', 'sslmode', 'serverVersion'];

    private const SCHEMES = ['sqlite' => 'pdo_sqlite', 'sqlite3' => 'sqlite3', 'mysql' => 'pdo_mysql', 'mysql2' => 'pdo_mysql', 'mariadb' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql', 'pgsql' => 'pdo_pgsql', 'pdo-sqlite' => 'pdo_sqlite', 'pdo-mysql' => 'pdo_mysql', 'pdo-pgsql' => 'pdo_pgsql'];

    /**
     * @param array<string, string> $options the transport's options (framework.messenger.transports.<name>.options)
     *
     * @return array{storage: string, total: int, rows: list<array{id: string, body: string, headers: array<mixed>, created_at: \DateTimeImmutable|null}>}
     *
     * @throws FailureTransportNotReadableException
     */
    public function read(string $dsn, array $options, ContainerConfiguration $configuration, int $limit): array
    {
        if (!class_exists(DriverManager::class)) {
            throw new FailureTransportNotReadableException('Reading a Doctrine transport needs doctrine/dbal, which is not installed.');
        }

        $parts = parse_url($dsn);
        if (false === $parts || !isset($parts['host'])) {
            throw new FailureTransportNotReadableException('The Doctrine transport DSN is invalid; it should look like "doctrine://default".');
        }
        parse_str($parts['query'] ?? '', $query);
        $options = $query + $options + self::DEFAULT_OPTIONS;
        $table = \is_string($options['table_name']) ? $options['table_name'] : '';
        $queue = \is_string($options['queue_name']) ? $options['queue_name'] : '';
        if (1 !== preg_match('/^\w+(\.\w+)?$/', $table)) {
            throw new FailureTransportNotReadableException(\sprintf('Unexpected table_name "%s".', $table));
        }
        $storage = \sprintf('%s (queue_name=%s)', $table, $queue);

        $connection = $this->connect($parts['host'], $configuration);
        try {
            if (!$connection->createSchemaManager()->tablesExist([$table])) {
                return ['storage' => $storage.', table does not exist yet', 'total' => 0, 'rows' => []];
            }

            $total = (int) $connection->fetchOne(\sprintf('SELECT COUNT(*) FROM %s WHERE queue_name = ?', $table), [$queue]);
            $rows = [];
            foreach ($connection->fetchAllAssociative(\sprintf('SELECT id, body, headers, created_at FROM %s WHERE queue_name = ? ORDER BY id DESC LIMIT %d', $table, $limit), [$queue]) as $row) {
                $headers = json_decode(\is_string($row['headers'] ?? null) ? $row['headers'] : '', true);
                $body = $row['body'] ?? '';
                $rows[] = [
                    'id' => (string) $row['id'],
                    'body' => \is_resource($body) ? (string) stream_get_contents($body) : (string) $body,
                    'headers' => \is_array($headers) ? $headers : [],
                    'created_at' => $this->date($row['created_at'] ?? null),
                ];
            }
        } catch (\Throwable $e) {
            throw new FailureTransportNotReadableException(\sprintf('Could not read "%s": "%s"', $storage, $e->getMessage()), 0, $e);
        } finally {
            $connection->close();
        }

        return ['storage' => $storage, 'total' => $total, 'rows' => array_reverse($rows)];
    }

    private function connect(string $name, ContainerConfiguration $configuration): Connection
    {
        $config = $configuration->doctrineConnection($name);
        if (null === $config) {
            throw new FailureTransportNotReadableException(\sprintf('The transport uses the Doctrine connection "%s", which is not in the container (doctrine.dbal.connections).', $name));
        }

        $params = array_intersect_key($config, array_flip(self::CONNECTION_KEYS));
        $url = $config['url'] ?? null;
        if (\is_string($url) && '' !== $url) {
            $params = array_filter((new DsnParser(self::SCHEMES))->parse($url), static fn (mixed $v): bool => null !== $v) + $params;
        }
        if (\is_string($config['dbname_suffix'] ?? null) && isset($params['dbname']) && \is_string($params['dbname'])) {
            $params['dbname'] .= $config['dbname_suffix'];
        }
        if (isset($params['port']) && is_numeric($params['port'])) {
            $params['port'] = (int) $params['port'];
        }
        if (!\is_string($params['driver'] ?? null)) {
            throw new FailureTransportNotReadableException(\sprintf('The Doctrine connection "%s" has neither a url nor a driver.', $name));
        }

        if (\in_array($params['driver'], ['pdo_sqlite', 'sqlite3'], true)) {
            if (true === ($params['memory'] ?? false) || ':memory:' === ($params['path'] ?? null)) {
                throw new FailureTransportNotReadableException(\sprintf('The Doctrine connection "%s" is an in-memory SQLite database; its messages only exist inside the worker process.', $name));
            }
            $path = \is_string($params['path'] ?? null) ? $params['path'] : '';
            if (!is_file($path)) {
                throw new FailureTransportNotReadableException(\sprintf('The SQLite database "%s" of the Doctrine connection "%s" does not exist.', $path, $name));
            }
            if ('pdo_sqlite' === $params['driver']) {
                $params['driverOptions'] = class_exists(\Pdo\Sqlite::class)
                    ? [\Pdo\Sqlite::ATTR_OPEN_FLAGS => \Pdo\Sqlite::OPEN_READONLY]
                    : [\PDO::SQLITE_ATTR_OPEN_FLAGS => \PDO::SQLITE_OPEN_READONLY];
            }
        }

        try {
            return DriverManager::getConnection($params);
        } catch (\Throwable $e) {
            throw new FailureTransportNotReadableException(\sprintf('Could not connect to the Doctrine connection "%s": "%s"', $name, $e->getMessage()), 0, $e);
        }
    }

    private function date(mixed $value): ?\DateTimeImmutable
    {
        if (!\is_string($value) || '' === $value) {
            return null;
        }

        try {
            // Stored by the transport as a UTC datetime without a zone.
            return new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
        } catch (\Exception) {
            return null;
        }
    }
}
