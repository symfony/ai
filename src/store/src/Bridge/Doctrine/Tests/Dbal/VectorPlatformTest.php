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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Bridge\Doctrine\Dbal\Metric;
use Symfony\AI\Store\Bridge\Doctrine\Dbal\Platforms\MariaDbVectorPlatform;
use Symfony\AI\Store\Bridge\Doctrine\Dbal\Platforms\PostgresVectorPlatform;
use Symfony\AI\Store\Bridge\Doctrine\Dbal\Platforms\SqliteVectorPlatform;
use Symfony\AI\Store\Bridge\Doctrine\Dbal\Platforms\VectorPlatformInterface;
use Symfony\AI\Store\Exception\InvalidArgumentException;

final class VectorPlatformTest extends TestCase
{
    #[DataProvider('provideColumnDeclarations')]
    public function testDeclaresItsVectorColumn(VectorPlatformInterface $platform, string $expected)
    {
        $this->assertSame($expected, $platform->getColumnDeclarationSql());
    }

    /**
     * @return iterable<string, array{VectorPlatformInterface, string}>
     */
    public static function provideColumnDeclarations(): iterable
    {
        yield 'postgres' => [new PostgresVectorPlatform(), 'vector'];
        yield 'sqlite' => [new SqliteVectorPlatform(), 'CLOB'];
        yield 'mariadb' => [new MariaDbVectorPlatform(), 'VECTOR'];
    }

    #[DataProvider('provideCosineDistances')]
    public function testExpressesACosineDistance(VectorPlatformInterface $platform, string $expected)
    {
        $parameter = $platform->getVectorParameterSql(':vector');

        $this->assertSame($expected, $platform->getDistanceSql('embedding', $parameter, Metric::Cosine));
    }

    /**
     * @return iterable<string, array{VectorPlatformInterface, string}>
     */
    public static function provideCosineDistances(): iterable
    {
        yield 'postgres' => [new PostgresVectorPlatform(), '(embedding <=> :vector)'];
        yield 'sqlite' => [new SqliteVectorPlatform(), 'vec_distance_cosine(embedding, :vector)'];
        yield 'mariadb' => [new MariaDbVectorPlatform(), 'VEC_DISTANCE_COSINE(embedding, VEC_FromText(:vector))'];
    }

    public function testMariaDbExpressesAnEuclideanDistance()
    {
        $platform = new MariaDbVectorPlatform();

        $this->assertSame('VEC_DISTANCE_EUCLIDEAN(embedding, VEC_FromText(:vector))', $platform->getDistanceSql('embedding', $platform->getVectorParameterSql(':vector'), Metric::Euclidean));
    }

    public function testMariaDbReadsAndWritesVectorsAsText()
    {
        $platform = new MariaDbVectorPlatform();

        $this->assertSame('VEC_ToText(embedding)', $platform->getVectorSelectSql('embedding'));
        $this->assertSame('[0.25,-1.5,3]', $platform->toDatabaseValue(new Vector([0.25, -1.5, 3.0])));
    }

    #[DataProvider('providePlatforms')]
    public function testRoundTripsAVector(VectorPlatformInterface $platform)
    {
        $vector = new Vector([0.25, -1.5, 3.0]);

        $this->assertSame([0.25, -1.5, 3.0], $platform->toVector($platform->toDatabaseValue($vector))->getData());
    }

    #[DataProvider('providePlatforms')]
    public function testWrapsParameterAndColumnConsistently(VectorPlatformInterface $platform)
    {
        $this->assertStringContainsString(':vector', $platform->getVectorParameterSql(':vector'));
        $this->assertStringContainsString('embedding', $platform->getVectorSelectSql('embedding'));
    }

    #[DataProvider('providePlatforms')]
    public function testRejectsAVectorLiteralItCannotRead(VectorPlatformInterface $platform)
    {
        $this->expectException(InvalidArgumentException::class);

        $platform->toVector('"not a vector"');
    }

    /**
     * @return iterable<string, array{VectorPlatformInterface}>
     */
    public static function providePlatforms(): iterable
    {
        yield 'postgres' => [new PostgresVectorPlatform()];
        yield 'sqlite' => [new SqliteVectorPlatform()];
        yield 'mariadb' => [new MariaDbVectorPlatform()];
    }

    #[DataProvider('providePlatformsWithoutInnerProduct')]
    public function testRefusesAMetricItDoesNotImplement(VectorPlatformInterface $platform)
    {
        $this->expectException(InvalidArgumentException::class);

        $platform->getDistanceSql('embedding', ':vector', Metric::InnerProduct);
    }

    /**
     * @return iterable<string, array{VectorPlatformInterface}>
     */
    public static function providePlatformsWithoutInnerProduct(): iterable
    {
        yield 'sqlite' => [new SqliteVectorPlatform()];
        yield 'mariadb' => [new MariaDbVectorPlatform()];
    }

    public function testSqliteStoresVectorsWithoutTheExtension()
    {
        $platform = new SqliteVectorPlatform();

        $this->assertSame(':vector', $platform->getVectorParameterSql(':vector'));
        $this->assertSame('embedding', $platform->getVectorSelectSql('embedding'));
    }
}
