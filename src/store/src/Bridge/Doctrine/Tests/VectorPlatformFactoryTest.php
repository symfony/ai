<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Doctrine\Tests;

use Doctrine\DBAL\Platforms\MariaDB110700Platform;
use Doctrine\DBAL\Platforms\MySQL84Platform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQL120Platform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Store\Bridge\Doctrine\VectorPlatform\MariaDbVectorPlatform;
use Symfony\AI\Store\Bridge\Doctrine\VectorPlatform\MySqlVectorPlatform;
use Symfony\AI\Store\Bridge\Doctrine\VectorPlatform\PostgresVectorPlatform;
use Symfony\AI\Store\Bridge\Doctrine\VectorPlatform\SqliteVectorPlatform;
use Symfony\AI\Store\Bridge\Doctrine\VectorPlatform\VectorPlatformFactory;
use Symfony\AI\Store\Exception\InvalidArgumentException;

final class VectorPlatformFactoryTest extends TestCase
{
    public function testPicksThePostgresDialect(): void
    {
        $this->assertInstanceOf(PostgresVectorPlatform::class, VectorPlatformFactory::create(new PostgreSQL120Platform()));
    }

    public function testPicksTheSqliteDialect(): void
    {
        $this->assertInstanceOf(SqliteVectorPlatform::class, VectorPlatformFactory::create(new SQLitePlatform()));
    }

    public function testPicksTheMySqlDialect(): void
    {
        $this->assertInstanceOf(MySqlVectorPlatform::class, VectorPlatformFactory::create(new MySQL84Platform()));
    }

    public function testPrefersMariaDbOverMySql(): void
    {
        // MariaDB's platform extends the MySQL one, so order decides this: matching MySQL first
        // would hand a MariaDB connection SQL functions it does not have.
        $this->assertInstanceOf(MariaDbVectorPlatform::class, VectorPlatformFactory::create(new MariaDB110700Platform()));
    }

    public function testRefusesAPlatformItHasNoDialectFor(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/no vector support/');

        VectorPlatformFactory::create(new OraclePlatform());
    }
}
