<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Tests\Distance;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Distance\DistanceCalculator;
use Symfony\AI\Store\Distance\DistanceStrategy;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\Component\Uid\Uuid;

final class DistanceCalculatorTest extends TestCase
{
    /**
     * @param array<float[]> $documentVectors
     * @param float[]        $queryVector
     * @param int[]          $expectedOrder
     */
    #[TestDox('Calculates distances correctly using $strategy strategy')]
    #[DataProvider('provideDistanceStrategyTestCases')]
    public function testCalculateWithDifferentStrategies(
        DistanceStrategy $strategy,
        array $documentVectors,
        array $queryVector,
        array $expectedOrder,
    ) {
        $calculator = new DistanceCalculator($strategy);

        $documents = [];
        foreach ($documentVectors as $index => $vector) {
            $documents[] = new VectorDocument(
                Uuid::v4(),
                new Vector($vector),
                new Metadata(['index' => $index])
            );
        }

        $result = $calculator->calculate($documents, new Vector($queryVector));

        // Check that results are ordered correctly
        $this->assertCount(\count($expectedOrder), $result);

        foreach ($expectedOrder as $position => $expectedIndex) {
            $metadata = $result[$position]->getMetadata();
            $this->assertSame($expectedIndex, $metadata['index']);
        }
    }

    /**
     * @return \Generator<string, array{DistanceStrategy, array<float[]>, float[], int[]}>
     */
    public static function provideDistanceStrategyTestCases(): \Generator
    {
        // Test vectors for different scenarios
        $vectors = [
            [1.0, 0.0, 0.0],  // Index 0: unit vector along x-axis
            [0.0, 1.0, 0.0],  // Index 1: unit vector along y-axis
            [0.0, 0.0, 1.0],  // Index 2: unit vector along z-axis
            [0.5, 0.5, 0.707], // Index 3: mixed vector
        ];

        $queryVector = [1.0, 0.0, 0.0]; // Query similar to first vector

        yield 'cosine distance' => [
            DistanceStrategy::COSINE_DISTANCE,
            $vectors,
            $queryVector,
            [0, 3, 1, 2], // Expected order: 0 is most similar (same direction)
        ];

        yield 'euclidean distance' => [
            DistanceStrategy::EUCLIDEAN_DISTANCE,
            $vectors,
            $queryVector,
            [0, 3, 1, 2], // Expected order: 0 is closest
        ];

        yield 'manhattan distance' => [
            DistanceStrategy::MANHATTAN_DISTANCE,
            $vectors,
            $queryVector,
            [0, 3, 1, 2], // Expected order based on L1 distance
        ];
    }

    #[TestDox('Limits results to specified maximum items')]
    public function testCalculateWithMaxItems()
    {
        $calculator = new DistanceCalculator(DistanceStrategy::EUCLIDEAN_DISTANCE);

        $documents = [
            new VectorDocument(Uuid::v4(), new Vector([0.0, 0.0]), new Metadata(['id' => 'a'])),
            new VectorDocument(Uuid::v4(), new Vector([1.0, 0.0]), new Metadata(['id' => 'b'])),
            new VectorDocument(Uuid::v4(), new Vector([0.0, 1.0]), new Metadata(['id' => 'c'])),
            new VectorDocument(Uuid::v4(), new Vector([1.0, 1.0]), new Metadata(['id' => 'd'])),
            new VectorDocument(Uuid::v4(), new Vector([0.5, 0.5]), new Metadata(['id' => 'e'])),
        ];

        $queryVector = new Vector([0.0, 0.0]);

        // Request only top 3 results
        $result = $calculator->calculate($documents, $queryVector, 3);

        $this->assertCount(3, $result);

        // Verify the closest 3 documents are returned
        // Distances from [0.0, 0.0]:
        // a: [0.0, 0.0] -> 0.0
        // b: [1.0, 0.0] -> 1.0
        // c: [0.0, 1.0] -> 1.0
        // d: [1.0, 1.0] -> sqrt(2) ≈ 1.414
        // e: [0.5, 0.5] -> sqrt(0.5) ≈ 0.707

        $ids = array_map(static fn ($doc) => $doc->getMetadata()['id'], $result);
        $this->assertSame(['a', 'e', 'b'], $ids); // a is closest, then e, then b/c (same distance)
    }

    #[TestDox('Calculates cosine distance correctly for parallel vectors')]
    public function testCosineDistanceCalculation()
    {
        $calculator = new DistanceCalculator(DistanceStrategy::COSINE_DISTANCE);

        // Test with parallel vectors (should have cosine distance = 0)
        $doc1 = new VectorDocument(Uuid::v4(), new Vector([1.0, 2.0, 3.0]));
        $doc2 = new VectorDocument(Uuid::v4(), new Vector([2.0, 4.0, 6.0])); // Parallel to doc1

        $queryVector = new Vector([1.0, 2.0, 3.0]);

        $result = $calculator->calculate([$doc1, $doc2], $queryVector);

        // Both vectors are parallel to query, so should have same cosine distance (0)
        $this->assertCount(2, $result);
    }

    #[TestDox('Calculates angular distance correctly for orthogonal vectors')]
    public function testAngularDistanceCalculation()
    {
        $calculator = new DistanceCalculator(DistanceStrategy::ANGULAR_DISTANCE);

        // Orthogonal vectors should have angular distance of 0.5 (90 degrees / 180 degrees)
        $orthogonalDoc = new VectorDocument(Uuid::v4(), new Vector([0.0, 1.0]));
        $parallelDoc = new VectorDocument(Uuid::v4(), new Vector([2.0, 0.0]));

        $queryVector = new Vector([1.0, 0.0]);

        $result = $calculator->calculate([$orthogonalDoc, $parallelDoc], $queryVector);

        // Parallel vector should be first (smaller angular distance)
        $this->assertEquals($parallelDoc->getId(), $result[0]->getId());
        $this->assertEquals($parallelDoc->getVector(), $result[0]->getVector());
        $this->assertNotNull($result[0]->getScore());
        $this->assertEquals($orthogonalDoc->getId(), $result[1]->getId());
        $this->assertEquals($orthogonalDoc->getVector(), $result[1]->getVector());
        $this->assertNotNull($result[1]->getScore());
    }

    #[TestDox('Calculates Chebyshev distance using maximum absolute difference')]
    public function testChebyshevDistanceCalculation()
    {
        $calculator = new DistanceCalculator(DistanceStrategy::CHEBYSHEV_DISTANCE);

        $doc1 = new VectorDocument(Uuid::v4(), new Vector([1.0, 2.0, 3.0]));
        $doc2 = new VectorDocument(Uuid::v4(), new Vector([1.5, 2.5, 3.5]));
        $doc3 = new VectorDocument(Uuid::v4(), new Vector([4.0, 2.0, 3.0]));

        $queryVector = new Vector([1.0, 2.0, 3.0]);

        $result = $calculator->calculate([$doc1, $doc2, $doc3], $queryVector);

        // doc1 should be first (distance 0), doc2 second (max diff 0.5), doc3 last (max diff 3.0)
        $this->assertEquals($doc1->getId(), $result[0]->getId());
        $this->assertNotNull($result[0]->getScore());
        $this->assertEquals($doc2->getId(), $result[1]->getId());
        $this->assertNotNull($result[1]->getScore());
        $this->assertEquals($doc3->getId(), $result[2]->getId());
        $this->assertNotNull($result[2]->getScore());
    }

    #[TestDox('Returns empty array when no documents are provided')]
    public function testEmptyDocumentsArray()
    {
        $calculator = new DistanceCalculator();

        $result = $calculator->calculate([], new Vector([1.0, 2.0, 3.0]));

        $this->assertSame([], $result);
    }

    #[TestDox('Returns single document when only one is provided')]
    public function testSingleDocument()
    {
        $calculator = new DistanceCalculator();

        $doc = new VectorDocument(Uuid::v4(), new Vector([1.0, 2.0, 3.0]));

        $result = $calculator->calculate([$doc], new Vector([0.0, 0.0, 0.0]));

        $this->assertCount(1, $result);
        $this->assertEquals($doc->getId(), $result[0]->getId());
        $this->assertEquals($doc->getVector(), $result[0]->getVector());
        $this->assertNotNull($result[0]->getScore());
    }

    #[TestDox('Handles high-dimensional vectors correctly')]
    public function testHighDimensionalVectors()
    {
        $calculator = new DistanceCalculator(DistanceStrategy::EUCLIDEAN_DISTANCE);

        $vector1 = array_fill(0, 100, 0.1);
        $vector2 = array_fill(0, 100, 0.2);

        $doc1 = new VectorDocument(Uuid::v4(), new Vector($vector1));
        $doc2 = new VectorDocument(Uuid::v4(), new Vector($vector2));

        $queryVector = new Vector(array_fill(0, 100, 0.15));

        $result = $calculator->calculate([$doc1, $doc2], $queryVector);

        // doc1 should be closer to query vector (0.15 is closer to 0.1 than to 0.2)
        $this->assertEquals($doc1->getId(), $result[0]->getId());
        $this->assertNotNull($result[0]->getScore());
        $this->assertEquals($doc2->getId(), $result[1]->getId());
        $this->assertNotNull($result[1]->getScore());
    }

    #[TestDox('Handles negative vector components correctly')]
    public function testNegativeVectorComponents()
    {
        $calculator = new DistanceCalculator(DistanceStrategy::EUCLIDEAN_DISTANCE);

        $doc1 = new VectorDocument(Uuid::v4(), new Vector([-1.0, -2.0, -3.0]));
        $doc2 = new VectorDocument(Uuid::v4(), new Vector([1.0, 2.0, 3.0]));
        $doc3 = new VectorDocument(Uuid::v4(), new Vector([0.0, 0.0, 0.0]));

        $queryVector = new Vector([-1.0, -2.0, -3.0]);

        $result = $calculator->calculate([$doc1, $doc2, $doc3], $queryVector);

        // doc1 should be first (identical to query)
        $this->assertEquals($doc1->getId(), $result[0]->getId());
        $this->assertNotNull($result[0]->getScore());
    }

    #[TestDox('Returns all documents when maxItems exceeds document count')]
    public function testMaxItemsGreaterThanDocumentCount()
    {
        $calculator = new DistanceCalculator();

        $doc1 = new VectorDocument(Uuid::v4(), new Vector([1.0, 0.0]));
        $doc2 = new VectorDocument(Uuid::v4(), new Vector([0.0, 1.0]));

        $result = $calculator->calculate([$doc1, $doc2], new Vector([1.0, 0.0]), 10);

        // Should return all documents even though maxItems is 10
        $this->assertCount(2, $result);
    }

    #[TestDox('Calculates Manhattan distance correctly with mixed positive and negative values')]
    public function testManhattanDistanceWithMixedSigns()
    {
        $calculator = new DistanceCalculator(DistanceStrategy::MANHATTAN_DISTANCE);

        $doc1 = new VectorDocument(Uuid::v4(), new Vector([1.0, -1.0, 2.0]));
        $doc2 = new VectorDocument(Uuid::v4(), new Vector([-1.0, 1.0, -2.0]));
        $doc3 = new VectorDocument(Uuid::v4(), new Vector([0.5, -0.5, 1.0]));

        $queryVector = new Vector([0.0, 0.0, 0.0]);

        $result = $calculator->calculate([$doc1, $doc2, $doc3], $queryVector);

        // doc3 has smallest Manhattan distance (2.0), then doc1 and doc2 (both 4.0)
        $this->assertEquals($doc3->getId(), $result[0]->getId());
        $this->assertNotNull($result[0]->getScore());
    }

    #[TestDox('Uses cosine distance as default strategy')]
    public function testDefaultStrategyIsCosineDistance()
    {
        $calculator = new DistanceCalculator();

        $doc1 = new VectorDocument(Uuid::v4(), new Vector([1.0, 0.0, 0.0]));
        $doc2 = new VectorDocument(Uuid::v4(), new Vector([100.0, 0.0, 0.0])); // Same direction but different magnitude

        $queryVector = new Vector([1.0, 0.0, 0.0]);

        $result = $calculator->calculate([$doc1, $doc2], $queryVector);

        // With cosine distance, both should have same distance (parallel vectors)
        // The order might vary but both are equally similar in terms of direction
        $this->assertCount(2, $result);
    }

    #[TestDox('Batched calculation returns same top results as full calculation')]
    public function testBatchedCalculationReturnsSameResultsAsFull()
    {
        $documents = [
            new VectorDocument(Uuid::v4(), new Vector([0.0, 0.0]), new Metadata(['id' => 'a'])),
            new VectorDocument(Uuid::v4(), new Vector([1.0, 0.0]), new Metadata(['id' => 'b'])),
            new VectorDocument(Uuid::v4(), new Vector([0.0, 1.0]), new Metadata(['id' => 'c'])),
            new VectorDocument(Uuid::v4(), new Vector([1.0, 1.0]), new Metadata(['id' => 'd'])),
            new VectorDocument(Uuid::v4(), new Vector([0.5, 0.5]), new Metadata(['id' => 'e'])),
        ];

        $queryVector = new Vector([0.0, 0.0]);

        $fullCalculator = new DistanceCalculator(DistanceStrategy::EUCLIDEAN_DISTANCE);
        $batchedCalculator = new DistanceCalculator(DistanceStrategy::EUCLIDEAN_DISTANCE, batchSize: 2);

        $fullResult = $fullCalculator->calculate($documents, $queryVector, 3);
        $batchedResult = $batchedCalculator->calculate($documents, $queryVector, 3);

        $fullIds = array_map(static fn (VectorDocument $doc): string => $doc->getMetadata()['id'], $fullResult);
        $batchedIds = array_map(static fn (VectorDocument $doc): string => $doc->getMetadata()['id'], $batchedResult);

        $this->assertSame($fullIds, $batchedIds);
        $this->assertCount(3, $batchedResult);
    }

    #[TestDox('Batched calculation prunes candidates after each batch')]
    public function testBatchedCalculationPrunesCandidates()
    {
        // 10 documents, batch size 3, maxItems 2
        // After each batch of 3, only the top 2 candidates are kept
        $documents = [];
        for ($i = 0; $i < 10; ++$i) {
            $documents[] = new VectorDocument(
                Uuid::v4(),
                new Vector([(float) $i, 0.0]),
                new Metadata(['id' => (string) $i]),
            );
        }

        $calculator = new DistanceCalculator(DistanceStrategy::EUCLIDEAN_DISTANCE, batchSize: 3);
        $result = $calculator->calculate($documents, new Vector([0.0, 0.0]), 2);

        $this->assertCount(2, $result);

        $ids = array_map(static fn (VectorDocument $doc): string => $doc->getMetadata()['id'], $result);
        $this->assertSame(['0', '1'], $ids);
    }

    #[TestDox('Batched calculation falls back to full when maxItems is null')]
    public function testBatchedCalculationFallsBackWithoutMaxItems()
    {
        $doc1 = new VectorDocument(Uuid::v4(), new Vector([1.0, 0.0]), new Metadata(['id' => 'a']));
        $doc2 = new VectorDocument(Uuid::v4(), new Vector([0.0, 1.0]), new Metadata(['id' => 'b']));

        $calculator = new DistanceCalculator(DistanceStrategy::EUCLIDEAN_DISTANCE, batchSize: 1);
        $result = $calculator->calculate([$doc1, $doc2], new Vector([1.0, 0.0]));

        // Without maxItems, all documents are returned (full calculation path)
        $this->assertCount(2, $result);
        $this->assertSame('a', $result[0]->getMetadata()['id']);
        $this->assertSame('b', $result[1]->getMetadata()['id']);
    }

    #[TestDox('Batched calculation works with batch size larger than document count')]
    public function testBatchedCalculationWithLargeBatchSize()
    {
        $documents = [
            new VectorDocument(Uuid::v4(), new Vector([0.0, 0.0]), new Metadata(['id' => 'a'])),
            new VectorDocument(Uuid::v4(), new Vector([1.0, 0.0]), new Metadata(['id' => 'b'])),
        ];

        $calculator = new DistanceCalculator(DistanceStrategy::EUCLIDEAN_DISTANCE, batchSize: 1000);
        $result = $calculator->calculate($documents, new Vector([0.0, 0.0]), 1);

        $this->assertCount(1, $result);
        $this->assertSame('a', $result[0]->getMetadata()['id']);
    }

    #[TestDox('Batched calculation returns empty array for empty documents')]
    public function testBatchedCalculationWithEmptyDocuments()
    {
        $calculator = new DistanceCalculator(batchSize: 100);

        $result = $calculator->calculate([], new Vector([1.0, 2.0]), 5);

        $this->assertSame([], $result);
    }

    #[TestDox('Batched calculation preserves scores')]
    public function testBatchedCalculationPreservesScores()
    {
        $doc = new VectorDocument(Uuid::v4(), new Vector([3.0, 4.0]));

        $fullCalculator = new DistanceCalculator(DistanceStrategy::EUCLIDEAN_DISTANCE);
        $batchedCalculator = new DistanceCalculator(DistanceStrategy::EUCLIDEAN_DISTANCE, batchSize: 1);

        $fullResult = $fullCalculator->calculate([$doc], new Vector([0.0, 0.0]), 1);
        $batchedResult = $batchedCalculator->calculate([$doc], new Vector([0.0, 0.0]), 1);

        $this->assertSame($fullResult[0]->getScore(), $batchedResult[0]->getScore());
        $this->assertEqualsWithDelta(5.0, $batchedResult[0]->getScore(), 0.0001);
    }

    /**
     * @param int[] $expectedOrder
     */
    #[TestDox('Batched calculation works with $strategy strategy')]
    #[DataProvider('provideBatchedStrategyTestCases')]
    public function testBatchedCalculationWithDifferentStrategies(DistanceStrategy $strategy, array $expectedOrder)
    {
        $documents = [
            new VectorDocument(Uuid::v4(), new Vector([1.0, 0.0, 0.0]), new Metadata(['index' => 0])),
            new VectorDocument(Uuid::v4(), new Vector([0.0, 1.0, 0.0]), new Metadata(['index' => 1])),
            new VectorDocument(Uuid::v4(), new Vector([0.0, 0.0, 1.0]), new Metadata(['index' => 2])),
            new VectorDocument(Uuid::v4(), new Vector([0.5, 0.5, 0.707]), new Metadata(['index' => 3])),
        ];

        $queryVector = new Vector([1.0, 0.0, 0.0]);

        $calculator = new DistanceCalculator($strategy, batchSize: 2);
        $result = $calculator->calculate($documents, $queryVector, 2);

        $this->assertCount(2, $result);

        foreach ($expectedOrder as $position => $expectedIndex) {
            $this->assertSame($expectedIndex, $result[$position]->getMetadata()['index']);
        }
    }

    /**
     * @return \Generator<string, array{DistanceStrategy, int[]}>
     */
    public static function provideBatchedStrategyTestCases(): \Generator
    {
        yield 'cosine distance batched' => [
            DistanceStrategy::COSINE_DISTANCE,
            [0, 3],
        ];

        yield 'euclidean distance batched' => [
            DistanceStrategy::EUCLIDEAN_DISTANCE,
            [0, 3],
        ];

        yield 'manhattan distance batched' => [
            DistanceStrategy::MANHATTAN_DISTANCE,
            [0, 3],
        ];
    }
}
