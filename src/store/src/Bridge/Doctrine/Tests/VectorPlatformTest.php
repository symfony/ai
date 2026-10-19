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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Bridge\Doctrine\Distance;
use Symfony\AI\Store\Bridge\Doctrine\VectorPlatform\PostgresVectorPlatform;
use Symfony\AI\Store\Bridge\Doctrine\VectorPlatform\SqliteVectorPlatform;
use Symfony\AI\Store\Bridge\Doctrine\VectorPlatform\VectorPlatformInterface;
use Symfony\AI\Store\Exception\InvalidArgumentException;

final class VectorPlatformTest extends TestCase
{
    #[DataProvider('provideColumnDeclarations')]
    public function testDeclaresItsVectorColumn(VectorPlatformInterface $platform, string $sized, string $unsized)
    {
        $this->assertSame($sized, $platform->getColumnDeclarationSql(1536));
        $this->assertSame($unsized, $platform->getUnsizedColumnDeclarationSql());
    }

    /**
     * @return iterable<string, array{VectorPlatformInterface, string, string}>
     */
    public static function provideColumnDeclarations(): iterable
    {
        yield 'postgres' => [new PostgresVectorPlatform(), 'vector(1536)', 'vector'];
        yield 'sqlite' => [new SqliteVectorPlatform(), 'TEXT', 'TEXT'];
    }

    #[DataProvider('provideCosineDistances')]
    public function testExpressesACosineDistance(VectorPlatformInterface $platform, string $expected)
    {
        $parameter = $platform->getVectorParameterSql(':vector');

        $this->assertSame($expected, $platform->getDistanceSql('embedding', $parameter, Distance::Cosine));
    }

    /**
     * @return iterable<string, array{VectorPlatformInterface, string}>
     */
    public static function provideCosineDistances(): iterable
    {
        yield 'postgres' => [new PostgresVectorPlatform(), '(embedding <=> :vector)'];
        yield 'sqlite' => [new SqliteVectorPlatform(), 'vec_distance_cosine(embedding, :vector)'];
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
        // Whatever wraps the parameter on the way in has to be undone on the way out, otherwise a
        // stored vector cannot be read back as one.
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
    }

    #[DataProvider('providePlatformsWithoutInnerProduct')]
    public function testRefusesADistanceItDoesNotImplement(VectorPlatformInterface $platform)
    {
        $this->expectException(InvalidArgumentException::class);

        $platform->getDistanceSql('embedding', ':vector', Distance::InnerProduct);
    }

    /**
     * @return iterable<string, array{VectorPlatformInterface}>
     */
    public static function providePlatformsWithoutInnerProduct(): iterable
    {
        yield 'sqlite' => [new SqliteVectorPlatform()];
    }

    public function testPostgresIndexesWithTheOpclassMatchingItsDistance()
    {
        $statements = (new PostgresVectorPlatform())->getSetupSql('star', 'embedding', 'star_embedding_idx', 1536, Distance::Cosine);

        $this->assertSame('CREATE EXTENSION IF NOT EXISTS vector', $statements[0]);
        $this->assertStringContainsString("NOT LIKE '%USING hnsw (embedding vector_cosine_ops)') THEN DROP INDEX star_embedding_idx", $statements[1]);
        $this->assertSame('ALTER TABLE star ALTER COLUMN embedding TYPE vector(1536)', $statements[2]);
        $this->assertSame('CREATE INDEX IF NOT EXISTS star_embedding_idx ON star USING hnsw (embedding vector_cosine_ops)', $statements[3]);
    }

    public function testPostgresDropsOnlyTheIndex()
    {
        $this->assertSame(['DROP INDEX IF EXISTS star_embedding_idx'], (new PostgresVectorPlatform())->getDropSql('star_embedding_idx'));
    }

    public function testSqliteHasNothingToSetUpOrDrop()
    {
        $platform = new SqliteVectorPlatform();

        $this->assertSame([], $platform->getSetupSql('star', 'embedding', 'star_embedding_idx', 1536, Distance::Cosine));
        $this->assertSame([], $platform->getDropSql('star_embedding_idx'));
    }

    public function testSqliteStoresVectorsWithoutTheExtension()
    {
        $platform = new SqliteVectorPlatform();

        // Only the distance functions come from sqlite-vec - writing and reading must work on
        // stock SQLite, or a test suite running on it could not even insert a row.
        $this->assertSame(':vector', $platform->getVectorParameterSql(':vector'));
        $this->assertSame('embedding', $platform->getVectorSelectSql('embedding'));
    }
}
