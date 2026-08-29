<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use Symfony\AI\Platform\Vector\VectorInterface;
use Symfony\AI\Store\Bridge\Doctrine\VectorPlatform\VectorPlatformFactory;
use Symfony\AI\Store\Bridge\Doctrine\VectorPlatform\VectorPlatformInterface;
use Symfony\AI\Store\Exception\InvalidArgumentException;

/**
 * Maps a vector column onto a VectorInterface, so an embedding can live as a plain field of an entity.
 *
 * Register the type and let Doctrine recognize the column when introspecting the database:
 *
 *     doctrine:
 *         dbal:
 *             types:
 *                 vector: Symfony\AI\Store\Bridge\Doctrine\Type\VectorType
 *             mapping_types:
 *                 vector: vector
 *
 * The field is declared without a size:
 *
 *     #[ORM\Column(type: 'vector', nullable: true)]
 *     private ?VectorInterface $embedding = null;
 *
 * The size belongs to the column, not to the mapping - see `getSQLDeclaration()` for why - and is
 * applied by `Store::setup()` or by the migration creating the column.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class VectorType extends Type
{
    public const NAME = 'vector';

    /**
     * The declaration deliberately carries no size.
     *
     * Doctrine decides whether a column changed by comparing the SQL both sides declare themselves
     * as, and no supported platform reports the size of a vector column when introspecting one. A
     * sized declaration would therefore never equal the column read back from the database, and
     * every schema diff from then on would want to re-`ALTER` a column that is already correct.
     *
     * The size is applied where it can be stated once and kept: `Store::setup()`, or the migration
     * that creates the column.
     *
     * @param array<string, mixed> $column
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $this->vectorPlatform($platform)->getUnsizedColumnDeclarationSql();
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
