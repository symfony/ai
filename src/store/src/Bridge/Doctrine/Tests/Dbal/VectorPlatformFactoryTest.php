<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Doctrine\Tests\Dbal;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Store\Bridge\Doctrine\Dbal\Platforms\PostgresVectorPlatform;
use Symfony\AI\Store\Bridge\Doctrine\Dbal\Platforms\SqliteVectorPlatform;
use Symfony\AI\Store\Bridge\Doctrine\Dbal\Platforms\VectorPlatformFactory;
use Symfony\AI\Store\Exception\InvalidArgumentException;

final class VectorPlatformFactoryTest extends TestCase
{
    public function testPicksThePostgresDialect()
    {
        $this->assertInstanceOf(PostgresVectorPlatform::class, VectorPlatformFactory::create(new PostgreSQLPlatform()));
    }

    public function testPicksTheSqliteDialect()
    {
        $this->assertInstanceOf(SqliteVectorPlatform::class, VectorPlatformFactory::create(new SQLitePlatform()));
    }

    #[DataProvider('provideUnsupportedPlatforms')]
    public function testRefusesAPlatformItHasNoDialectFor(AbstractPlatform $platform)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('Database platform "%s" has no vector support, use PostgreSQL with pgvector or SQLite with sqlite-vec.', $platform::class));

        VectorPlatformFactory::create($platform);
    }

    /**
     * @return iterable<string, array{AbstractPlatform}>
     */
    public static function provideUnsupportedPlatforms(): iterable
    {
        yield 'mariadb' => [new MariaDBPlatform()];
        yield 'mysql' => [new MySQLPlatform()];
        yield 'oracle' => [new OraclePlatform()];
    }
}
