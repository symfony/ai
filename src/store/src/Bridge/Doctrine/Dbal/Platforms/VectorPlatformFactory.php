<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Doctrine\Dbal\Platforms;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Symfony\AI\Store\Exception\InvalidArgumentException;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class VectorPlatformFactory
{
    public static function create(AbstractPlatform $platform): VectorPlatformInterface
    {
        return match (true) {
            $platform instanceof PostgreSQLPlatform => new PostgresVectorPlatform(),
            $platform instanceof SQLitePlatform => new SqliteVectorPlatform(),
            $platform instanceof MariaDBPlatform => new MariaDbVectorPlatform(),
            $platform instanceof MySQLPlatform => throw new InvalidArgumentException('MySQL has no function to compare vectors outside of HeatWave, use PostgreSQL with pgvector, MariaDB or SQLite with sqlite-vec.'),
            default => throw new InvalidArgumentException(\sprintf('Database platform "%s" has no vector support, use PostgreSQL with pgvector, MariaDB or SQLite with sqlite-vec.', $platform::class)),
        };
    }
}
