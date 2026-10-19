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

use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
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
    public function testPicksThePostgresDialect()
    {
        $this->assertInstanceOf(PostgresVectorPlatform::class, VectorPlatformFactory::create(new PostgreSQLPlatform()));
    }

    public function testPicksTheSqliteDialect()
    {
        $this->assertInstanceOf(SqliteVectorPlatform::class, VectorPlatformFactory::create(new SQLitePlatform()));
    }

    public function testPicksTheMySqlDialect()
    {
        $this->assertInstanceOf(MySqlVectorPlatform::class, VectorPlatformFactory::create(new MySQLPlatform()));
    }

    public function testPrefersMariaDbOverMySql()
    {
        // MariaDB's platform extends the MySQL one, so order decides this: matching MySQL first
        // would hand a MariaDB connection SQL functions it does not have.
        $this->assertInstanceOf(MariaDbVectorPlatform::class, VectorPlatformFactory::create(new MariaDBPlatform()));
    }

    public function testRefusesAPlatformItHasNoDialectFor()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/no vector support/');

        VectorPlatformFactory::create(new OraclePlatform());
    }
}
