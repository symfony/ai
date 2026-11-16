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
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Types\Type;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Bridge\Doctrine\Dbal\Types\VectorType;
use Symfony\AI\Store\Exception\InvalidArgumentException;

final class VectorTypeTest extends TestCase
{
    #[DataProvider('providePlatforms')]
    public function testDeclaresTheColumnWithoutASize(AbstractPlatform $platform, string $expected)
    {
        $type = new VectorType();

        $this->assertSame($expected, $type->getSQLDeclaration(['platformOptions' => ['dimensions' => 1536]], $platform));
        $this->assertSame($expected, $type->getSQLDeclaration([], $platform));
    }

    /**
     * @return iterable<string, array{AbstractPlatform, string}>
     */
    public static function providePlatforms(): iterable
    {
        yield 'postgres' => [new PostgreSQLPlatform(), 'vector'];
        yield 'sqlite' => [new SQLitePlatform(), 'CLOB'];
    }

    public function testMapsTheVectorTypeOfPostgres()
    {
        if (!Type::hasType(VectorType::NAME)) {
            Type::addType(VectorType::NAME, VectorType::class);
        }

        $this->assertTrue((new PostgreSQLPlatform())->hasDoctrineTypeMappingFor('vector'));
        $this->assertSame(VectorType::NAME, (new PostgreSQLPlatform())->getDoctrineTypeMapping('vector'));
        $this->assertFalse((new SQLitePlatform())->hasDoctrineTypeMappingFor('vector'));
    }

    public function testRoundTripsAVectorThroughTheDatabase()
    {
        $type = new VectorType();
        $platform = new PostgreSQLPlatform();

        $stored = $type->convertToDatabaseValue(new Vector([1.5, -0.5]), $platform);

        $this->assertSame('[1.5,-0.5]', $stored);
        $this->assertSame([1.5, -0.5], $type->convertToPHPValue($stored, $platform)?->getData());
    }

    public function testKeepsNullOnBothSides()
    {
        $type = new VectorType();
        $platform = new PostgreSQLPlatform();

        $this->assertNull($type->convertToDatabaseValue(null, $platform));
        $this->assertNull($type->convertToPHPValue(null, $platform));
    }

    public function testLeavesTheParameterUnwrapped()
    {
        $type = new VectorType();

        $this->assertSame(':vector', $type->convertToDatabaseValueSQL(':vector', new PostgreSQLPlatform()));
        $this->assertSame(':vector', $type->convertToDatabaseValueSQL(':vector', new SQLitePlatform()));
    }

    public function testRefusesToStoreSomethingThatIsNotAVector()
    {
        $this->expectException(InvalidArgumentException::class);

        (new VectorType())->convertToDatabaseValue([1.0, 2.0], new PostgreSQLPlatform());
    }

    public function testPassesAnAlreadyConvertedVectorThrough()
    {
        $vector = new Vector([1.0]);

        $this->assertSame($vector, (new VectorType())->convertToPHPValue($vector, new PostgreSQLPlatform()));
    }
}
