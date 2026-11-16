<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Doctrine\Dbal\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Types\Type;
use Symfony\AI\Platform\Vector\VectorInterface;
use Symfony\AI\Store\Bridge\Doctrine\Dbal\Platforms\VectorPlatformFactory;
use Symfony\AI\Store\Bridge\Doctrine\Dbal\Platforms\VectorPlatformInterface;
use Symfony\AI\Store\Exception\InvalidArgumentException;

/**
 * Maps a vector column onto a VectorInterface.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class VectorType extends Type
{
    public const NAME = 'vector';

    /**
     * Unsized, as introspection reports no size and a sized declaration would show up in every schema diff.
     *
     * @param array<string, mixed> $column
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $this->vectorPlatform($platform)->getColumnDeclarationSql();
    }

    /**
     * @return list<string>
     */
    public function getMappedDatabaseTypes(AbstractPlatform $platform): array
    {
        return $platform instanceof PostgreSQLPlatform || $platform instanceof MariaDBPlatform ? ['vector'] : [];
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof VectorInterface) {
            throw new InvalidArgumentException(\sprintf('Expected a "%s" instance, got "%s".', VectorInterface::class, get_debug_type($value)));
        }

        return $this->vectorPlatform($platform)->toDatabaseValue($value);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?VectorInterface
    {
        if (null === $value || $value instanceof VectorInterface) {
            return $value;
        }

        if (!\is_string($value)) {
            throw new InvalidArgumentException(\sprintf('Expected a string or null, got "%s".', get_debug_type($value)));
        }

        return $this->vectorPlatform($platform)->toVector($value);
    }

    public function convertToDatabaseValueSQL(string $sqlExpr, AbstractPlatform $platform): string
    {
        return $this->vectorPlatform($platform)->getVectorParameterSql($sqlExpr);
    }

    public function convertToPHPValueSQL(string $sqlExpr, AbstractPlatform $platform): string
    {
        return $this->vectorPlatform($platform)->getVectorSelectSql($sqlExpr);
    }

    private function vectorPlatform(AbstractPlatform $platform): VectorPlatformInterface
    {
        return VectorPlatformFactory::create($platform);
    }
}
