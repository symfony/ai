<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\E2E;

use PHPUnit\Framework\Assert;
use Symfony\Component\Process\Process;

/**
 * The vector store of the blog, driven through the console commands of the Store component.
 *
 * The commands run in the `dev` environment, against the PostgreSQL of the Docker setup and the
 * real embeddings of OpenAI - the same store the browser then queries through the blog agent.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Store
{
    /**
     * Indexing the Symfony blog embeds every post through OpenAI, which takes a while.
     */
    private const int INDEX_TIMEOUT = 600;

    private const string SERVICE = 'ai.store.postgres.symfony_blog';
    private const string INDEXER = 'blog';
    private const string RETRIEVER = 'blog';
    private const string TABLE = 'symfony_blog';

    /**
     * Creates the table, the pgvector extension and the index - idempotent, the store issues its
     * statements with `IF NOT EXISTS`.
     */
    public static function setup(): string
    {
        return self::console('ai:store:setup', self::SERVICE);
    }

    /**
     * Whether the database of the demo is up, and therefore the store usable at all.
     */
    public static function isAvailable(): bool
    {
        return null !== self::connect();
    }

    /**
     * The number of indexed documents, and zero when the table does not exist yet.
     */
    public static function documents(): int
    {
        $connection = self::connect();

        if (null === $connection) {
            return 0;
        }

        try {
            $result = $connection->query(\sprintf('SELECT count(*) FROM "%s"', self::TABLE));

            return false === $result ? 0 : (int) $result->fetchColumn();
        } catch (\PDOException) {
            return 0;
        }
    }

    /**
     * Removes the table with everything in it.
     */
    public static function drop(): string
    {
        return self::console('ai:store:drop', self::SERVICE, '--force');
    }

    /**
     * Loads the RSS feed of the Symfony blog, filters, splits and embeds it into the store.
     *
     * The documents are indexed under a fresh id every run, so indexing twice does not refresh the
     * store - it duplicates it. Drop the store first to rebuild it. Once `StoreInterface::clear()`
     * lands, that becomes an `ai:store:clear` and keeps the table and its index around.
     */
    public static function index(): string
    {
        return self::console('ai:store:index', self::INDEXER);
    }

    public static function retrieve(string $query, int $limit = 3): string
    {
        return self::console('ai:store:retrieve', self::RETRIEVER, $query, '--limit='.$limit);
    }

    /**
     * Runs a console command of the demo in the `dev` environment, and fails loudly - a store that
     * did not index is more useful as an error than as an empty search result.
     */
    private static function console(string ...$arguments): string
    {
        Environment::expose();

        $process = new Process(
            ['php', 'bin/console', ...$arguments, '--env=dev', '--no-interaction'],
            Environment::projectDirectory(),
            timeout: self::INDEX_TIMEOUT,
        );

        $process->run();

        if (!$process->isSuccessful()) {
            Assert::fail(\sprintf(
                "The command \"%s\" failed:\n%s\n%s",
                implode(' ', $arguments),
                $process->getOutput(),
                $process->getErrorOutput(),
            ));
        }

        return $process->getOutput();
    }

    private static function connect(): ?\PDO
    {
        Environment::expose();

        $url = parse_url($_ENV['DATABASE_URL'] ?? $_SERVER['DATABASE_URL'] ?? '');

        if (!\is_array($url) || !isset($url['host'])) {
            return null;
        }

        try {
            return new \PDO(
                \sprintf('pgsql:host=%s;port=%d;dbname=%s', $url['host'], $url['port'] ?? 5432, ltrim($url['path'] ?? '', '/')),
                urldecode($url['user'] ?? ''),
                urldecode($url['pass'] ?? ''),
            );
        } catch (\PDOException) {
            return null;
        }
    }
}
