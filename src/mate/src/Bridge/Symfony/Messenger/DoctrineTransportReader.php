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

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\MalformedDsnException;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\DBAL\Tools\DsnParser;
use Symfony\AI\Mate\Bridge\Symfony\Exception\FailureTransportNotReadableException;

/**
 * Reads a Doctrine Messenger transport with SELECTs only, on a read-only session where the platform allows it.
 *
 * @internal
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class DoctrineTransportReader
{
    public const ROW_BYTES = 1024 * 1024;
    public const BUDGET_BYTES = 32 * 1024 * 1024;
    private const CHUNK = 50;

    private const DEFAULT_OPTIONS = ['table_name' => 'messenger_messages', 'queue_name' => 'default'];

    private const CONNECTION_KEYS = ['driver', 'host', 'port', 'user', 'password', 'dbname', 'path', 'memory', 'charset', 'unix_socket',
        'sslmode', 'sslrootcert', 'sslcert', 'sslkey', 'sslcrl', 'application_name', 'serverVersion', 'driverOptions'];

    private const SCHEMES = ['sqlite' => 'pdo_sqlite', 'sqlite3' => 'sqlite3', 'mysql' => 'pdo_mysql', 'mysql2' => 'pdo_mysql', 'mariadb' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql', 'pgsql' => 'pdo_pgsql', 'pdo-sqlite' => 'pdo_sqlite', 'pdo-mysql' => 'pdo_mysql', 'pdo-pgsql' => 'pdo_pgsql'];

    public function __construct(
        private readonly int $rowBytes = self::ROW_BYTES,
        private readonly int $budgetBytes = self::BUDGET_BYTES,
    ) {
    }

    /**
     * Calls $onRow(id, body, headers, created_at) for each stored message, newest first.
     *
     * @param array<string, string>                                                 $options the transport's options (framework.messenger.transports.<name>.options)
     * @param callable(string, string, array<mixed>, \DateTimeImmutable|null): void $onRow
     *
     * @return array{storage: string, total: int, scanned: int, skipped: list<array{id: string, reason: string}>, truncated: string|null}
     *
     * @throws FailureTransportNotReadableException
     */
    public function read(string $dsn, array $options, ContainerConfiguration $configuration, int $limit, callable $onRow): array
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
        // Interpolated unquoted, as the transport does: the pattern keeps it an identifier.
        if (1 !== preg_match('/^\w+(\.\w+)?$/', $table)) {
            throw new FailureTransportNotReadableException(\sprintf('Unexpected table_name "%s".', $table));
        }
        $storage = \sprintf('%s (queue_name=%s)', $table, $queue);

        $connection = $this->connect($parts['host'], $configuration);
        try {
            $platform = $connection->getDatabasePlatform();
            $total = (int) $connection->createQueryBuilder()->select('COUNT(*)')->from($table)->where('queue_name = ?')->setParameter(0, $queue)->executeQuery()->fetchOne();
            $index = $connection->createQueryBuilder()
                ->select('id', 'created_at', $platform->getLengthExpression('body').' AS body_length', $platform->getLengthExpression('headers').' AS headers_length')
                ->from($table)->where('queue_name = ?')->setParameter(0, $queue)
                ->orderBy('id', 'DESC')->setMaxResults($limit)
                ->executeQuery()->fetchAllAssociative();

            $skipped = [];
            $wanted = [];
            $bytes = 0;
            $truncated = $total > \count($index) ? \sprintf('only the newest %d messages are read', $limit) : null;
            foreach ($index as $row) {
                $size = (int) $row['body_length'] + (int) $row['headers_length'];
                if ($size > $this->rowBytes) {
                    $skipped[] = ['id' => (string) $row['id'], 'reason' => \sprintf('The message is %s, above the %s this tool reads per message.', $this->size($size), $this->size($this->rowBytes))];
                    continue;
                }
                if ($bytes + $size > $this->budgetBytes) {
                    $truncated = \sprintf('reading stopped at the %s budget for stored messages', $this->size($this->budgetBytes));
                    break;
                }
                $bytes += $size;
                $wanted[(string) $row['id']] = $this->date($row['created_at'] ?? null);
            }

            foreach (array_chunk(array_keys($wanted), self::CHUNK) as $ids) {
                $result = $connection->createQueryBuilder()->select('id', 'body', 'headers')->from($table)
                    ->where('id IN (:ids)')->setParameter('ids', array_map('intval', $ids), ArrayParameterType::INTEGER)
                    ->orderBy('id', 'DESC')->executeQuery();
                while (false !== $row = $result->fetchAssociative()) {
                    $body = $row['body'] ?? '';
                    $headers = json_decode(\is_string($row['headers'] ?? null) ? $row['headers'] : '', true);
                    $onRow((string) $row['id'], \is_resource($body) ? (string) stream_get_contents($body) : (string) $body, \is_array($headers) ? $headers : [], $wanted[(string) $row['id']] ?? null);
                }
            }
        } catch (TableNotFoundException) {
            return ['storage' => $storage.', table does not exist yet', 'total' => 0, 'scanned' => 0, 'skipped' => [], 'truncated' => null];
        } catch (FailureTransportNotReadableException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new FailureTransportNotReadableException(\sprintf('Could not read "%s": "%s"', $storage, $e->getMessage()), 0, $e);
        } finally {
            $connection->close();
        }

        return ['storage' => $storage, 'total' => $total, 'scanned' => \count($wanted), 'skipped' => $skipped, 'truncated' => $truncated];
    }

    private function connect(string $name, ContainerConfiguration $configuration): Connection
    {
        $config = $configuration->doctrineConnection($name);
        if (null === $config) {
            throw new FailureTransportNotReadableException(\sprintf('The transport uses the Doctrine connection "%s", which is not in the container (doctrine.dbal.connections).', $name));
        }

        if (isset($config['server_version']) && !isset($config['serverVersion'])) {
            $config['serverVersion'] = $config['server_version'];
        }
        $params = array_intersect_key($config, array_flip(self::CONNECTION_KEYS));
        $url = $config['url'] ?? null;
        if (\is_string($url) && '' !== $url) {
            try {
                $params = array_filter((new DsnParser(self::SCHEMES))->parse($url), static fn (mixed $v): bool => null !== $v) + $params;
            } catch (MalformedDsnException) {
                throw new FailureTransportNotReadableException(\sprintf('The url of the Doctrine connection "%s" is not a valid DSN.', $name));
            }
        }
        if (\is_string($config['dbname_suffix'] ?? null) && isset($params['dbname']) && \is_string($params['dbname'])) {
            $params['dbname'] .= $config['dbname_suffix'];
        }
        if (isset($params['port']) && is_numeric($params['port'])) {
            $params['port'] = (int) $params['port'];
        }
        $driver = $params['driver'] ?? null;
        if (!\is_string($driver)) {
            throw new FailureTransportNotReadableException(\sprintf('The Doctrine connection "%s" has neither a url nor a driver.', $name));
        }

        $readOnly = null;
        if (\in_array($driver, ['pdo_sqlite', 'sqlite3'], true)) {
            if (true === ($params['memory'] ?? false) || ':memory:' === ($params['path'] ?? null)) {
                throw new FailureTransportNotReadableException(\sprintf('The Doctrine connection "%s" is an in-memory SQLite database; its messages only exist inside the worker process.', $name));
            }
            $path = \is_string($params['path'] ?? null) ? $params['path'] : '';
            if (!is_file($path)) {
                throw new FailureTransportNotReadableException(\sprintf('The SQLite database "%s" of the Doctrine connection "%s" does not exist.', $path, $name));
            }
            if ('pdo_sqlite' === $driver) {
                $options = \is_array($params['driverOptions'] ?? null) ? $params['driverOptions'] : [];
                $params['driverOptions'] = (class_exists(\Pdo\Sqlite::class)
                    ? [\Pdo\Sqlite::ATTR_OPEN_FLAGS => \Pdo\Sqlite::OPEN_READONLY]
                    : [\PDO::SQLITE_ATTR_OPEN_FLAGS => \PDO::SQLITE_OPEN_READONLY]) + $options;
            }
            $readOnly = 'PRAGMA query_only = ON';
        } elseif (\in_array($driver, ['pdo_mysql', 'mysqli'], true)) {
            $readOnly = 'SET SESSION TRANSACTION READ ONLY';
        } elseif (\in_array($driver, ['pdo_pgsql', 'pgsql'], true)) {
            $readOnly = 'SET SESSION CHARACTERISTICS AS TRANSACTION READ ONLY';
        }

        try {
            $connection = DriverManager::getConnection($params);
            if (null !== $readOnly) {
                $connection->executeStatement($readOnly);
            }

            return $connection;
        } catch (\Throwable $e) {
            throw new FailureTransportNotReadableException(\sprintf('Could not open the Doctrine connection "%s" read-only: "%s"', $name, $e->getMessage()), 0, $e);
        }
    }

    private function size(int $bytes): string
    {
        return $bytes >= 1024 * 1024 ? \sprintf('%d MB', intdiv($bytes, 1024 * 1024)) : \sprintf('%d KB', max(1, intdiv($bytes, 1024)));
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
