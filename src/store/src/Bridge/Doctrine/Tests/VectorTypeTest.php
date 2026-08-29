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

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDB110700Platform;
use Doctrine\DBAL\Platforms\PostgreSQL120Platform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Bridge\Doctrine\Type\VectorType;
use Symfony\AI\Store\Exception\InvalidArgumentException;

final class VectorTypeTest extends TestCase
{
    #[DataProvider('providePlatforms')]
    public function testDeclaresTheColumnWithoutASize(AbstractPlatform $platform, string $expected): void
    {
        $type = new VectorType();

        // Both sides of a schema comparison have to produce the same declaration, and the column
        // read back from the database has no size to report - so neither side may state one.
        $this->assertSame($expected, $type->getSQLDeclaration(['platformOptions' => ['dimensions' => 1536]], $platform));
        $this->assertSame($expected, $type->getSQLDeclaration([], $platform));
    }

    /**
     * @return iterable<string, array{AbstractPlatform, string}>
     */
    public static function providePlatforms(): iterable
    {
        yield 'postgres' => [new PostgreSQL120Platform(), 'vector'];
        yield 'mariadb' => [new MariaDB110700Platform(), 'VECTOR'];
        yield 'sqlite' => [new SQLitePlatform(), 'TEXT'];
    }

    public function testRoundTripsAVectorThroughTheDatabase(): void
    {
        $type = new VectorType();
        $platform = new PostgreSQL120Platform();

        $stored = $type->convertToDatabaseValue(new Vector([1.5, -0.5]), $platform);

        $this->assertSame('[1.5,-0.5]', $stored);
        $this->assertSame([1.5, -0.5], $type->convertToPHPValue($stored, $platform)?->getData());
    }

    public function testKeepsNullOnBothSides(): void
    {
        $type = new VectorType();
        $platform = new PostgreSQL120Platform();

        $this->assertNull($type->convertToDatabaseValue(null, $platform));
        $this->assertNull($type->convertToPHPValue(null, $platform));
    }

    public function testWrapsTheParameterOnlyWhereThePlatformNeedsIt(): void
    {
        $type = new VectorType();

        // Postgres coerces the text literal in assignment context, MariaDB needs the function -
        // and SQLite must stay untouched so that writing works without the sqlite-vec extension.
        $this->assertSame(':vector', $type->convertToDatabaseValueSQL(':vector', new PostgreSQL120Platform()));
        $this->assertSame('VEC_FromText(:vector)', $type->convertToDatabaseValueSQL(':vector', new MariaDB110700Platform()));
        $this->assertSame(':vector', $type->convertToDatabaseValueSQL(':vector', new SQLitePlatform()));
    }

    public function testRefusesToStoreSomethingThatIsNotAVector(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new VectorType())->convertToDatabaseValue([1.0, 2.0], new PostgreSQL120Platform());
    }

    public function testPassesAnAlreadyConvertedVectorThrough(): void
    {
        $vector = new Vector([1.0]);

        $this->assertSame($vector, (new VectorType())->convertToPHPValue($vector, new PostgreSQL120Platform()));
    }
}
