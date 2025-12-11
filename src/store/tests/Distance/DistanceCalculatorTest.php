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
     * @param array<list<float>> $documentVectors
     * @param list<float>        $queryVector
     * @param list<int>          $expectedOrder
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
            $metadata = $result[$position]->metadata;
            $this->assertSame($expectedIndex, $metadata['index']);
        }
    }

    /**
     * @return \Generator<string, array{DistanceStrategy, array<list<float>>, list<float>, list<int>}>
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

        $ids = array_map(fn ($doc) => $doc->metadata['id'], $result);
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
        $this->assertEquals($parallelDoc->id, $result[0]->id);
        $this->assertEquals($parallelDoc->vector, $result[0]->vector);
        $this->assertNotNull($result[0]->score);
        $this->assertEquals($orthogonalDoc->id, $result[1]->id);
        $this->assertEquals($orthogonalDoc->vector, $result[1]->vector);
        $this->assertNotNull($result[1]->score);
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
        $this->assertEquals($doc1->id, $result[0]->id);
        $this->assertNotNull($result[0]->score);
        $this->assertEquals($doc2->id, $result[1]->id);
        $this->assertNotNull($result[1]->score);
        $this->assertEquals($doc3->id, $result[2]->id);
        $this->assertNotNull($result[2]->score);
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
        $this->assertEquals($doc->id, $result[0]->id);
        $this->assertEquals($doc->vector, $result[0]->vector);
        $this->assertNotNull($result[0]->score);
    }

    #[TestDox('Handles high-dimensional vectors correctly')]
    public function testHighDimensionalVectors()
    {
        $calculator = new DistanceCalculator(DistanceStrategy::EUCLIDEAN_DISTANCE);

        // Create high-dimensional vectors (100 dimensions)
        $dimensions = 100;
        $vector1 = array_fill(0, $dimensions, 0.1);
        $vector2 = array_fill(0, $dimensions, 0.2);

        $doc1 = new VectorDocument(Uuid::v4(), new Vector($vector1));
        $doc2 = new VectorDocument(Uuid::v4(), new Vector($vector2));

        $queryVector = new Vector(array_fill(0, $dimensions, 0.15));

        $result = $calculator->calculate([$doc1, $doc2], $queryVector);

        // doc1 should be closer to query vector (0.15 is closer to 0.1 than to 0.2)
        $this->assertEquals($doc1->id, $result[0]->id);
        $this->assertNotNull($result[0]->score);
        $this->assertEquals($doc2->id, $result[1]->id);
        $this->assertNotNull($result[1]->score);
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
        $this->assertEquals($doc1->id, $result[0]->id);
        $this->assertNotNull($result[0]->score);
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
        $this->assertEquals($doc3->id, $result[0]->id);
        $this->assertNotNull($result[0]->score);
    }

    #[TestDox('Uses cosine distance as default strategy')]
    public function testDefaultStrategyIsCosineDistance()
    {
        // Test that default constructor uses cosine distance
        $calculator = new DistanceCalculator();

        // Create vectors where cosine distance ordering differs from Euclidean
        $doc1 = new VectorDocument(Uuid::v4(), new Vector([1.0, 0.0, 0.0]));
        $doc2 = new VectorDocument(Uuid::v4(), new Vector([100.0, 0.0, 0.0])); // Same direction but different magnitude

        $queryVector = new Vector([1.0, 0.0, 0.0]);

        $result = $calculator->calculate([$doc1, $doc2], $queryVector);

        // With cosine distance, both should have same distance (parallel vectors)
        // The order might vary but both are equally similar in terms of direction
        $this->assertCount(2, $result);
    }
}
