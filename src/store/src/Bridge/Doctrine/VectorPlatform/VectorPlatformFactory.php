<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Doctrine\VectorPlatform;

use Doctrine\DBAL\Platforms\AbstractPlatform;
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
            default => throw new InvalidArgumentException(\sprintf('Database platform "%s" has no vector support in this bridge, pass a "%s" implementation explicitly.', $platform::class, VectorPlatformInterface::class)),
        };
    }
}
