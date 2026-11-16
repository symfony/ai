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

use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Store\Bridge\Doctrine\Dbal\Platforms\MariaDbVectorPlatform;
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

    public function testPicksTheMariaDbDialect()
    {
        $this->assertInstanceOf(MariaDbVectorPlatform::class, VectorPlatformFactory::create(new MariaDBPlatform()));
    }

    public function testExplainsWhyMySqlIsNotSupported()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('MySQL has no function to compare vectors outside of HeatWave');

        VectorPlatformFactory::create(new MySQLPlatform());
    }

    public function testRefusesAPlatformItHasNoDialectFor()
    {
        $platform = new OraclePlatform();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('Database platform "%s" has no vector support, use PostgreSQL with pgvector, MariaDB or SQLite with sqlite-vec.', $platform::class));

        VectorPlatformFactory::create($platform);
    }
}
