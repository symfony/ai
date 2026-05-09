<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Distance;

use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\VectorDocument;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class DistanceCalculator
{
    /**
     * @param positive-int $batchSize when set alongside $maxItems in {@see self::calculate()}, documents are scored in chunks of this size
     */
    public function __construct(
        private readonly DistanceStrategy $strategy = DistanceStrategy::COSINE_DISTANCE,
        private readonly int $batchSize = 100,
    ) {
    }

    /**
     * @param VectorDocument[] $documents
     * @param ?int             $maxItems  If maxItems is provided, only the top N results will be returned
     *
     * @return VectorDocument[]
     */
    public function calculate(array $documents, Vector $vector, ?int $maxItems = null): array
    {
        if (null !== $maxItems && $maxItems > 0 && $this->batchSize <= \count($documents)) {
            return $this->calculateBatched($documents, $vector, $maxItems);
        }

        return $this->calculateAgainstAll($documents, $vector, $maxItems);
    }

    /**
     * @param VectorDocument[] $documents
     *
     * @return VectorDocument[]
     */
    private function calculateAgainstAll(array $documents, Vector $vector, ?int $maxItems): array
    {
        $strategy = $this->resolveStrategy();

        $currentEmbeddings = array_map(
            static fn (VectorDocument $vectorDocument): array => [
                'distance' => $strategy($vectorDocument, $vector),
                'document' => $vectorDocument,
            ],
            $documents,
        );

        usort(
            $currentEmbeddings,
            static fn (array $embedding, array $nextEmbedding): int => $embedding['distance'] <=> $nextEmbedding['distance'],
        );

        if (null !== $maxItems && $maxItems < \count($currentEmbeddings)) {
            $currentEmbeddings = \array_slice($currentEmbeddings, 0, $maxItems);
        }

        return array_map(
            static fn (array $embedding): VectorDocument => $embedding['document']->withScore($embedding['distance']),
            $currentEmbeddings,
        );
    }

    /**
     * Processes documents in chunks of {@see self::$batchSize}, keeping only the top $maxItems candidates after each chunk.
     *
     * @param VectorDocument[] $documents
     * @param positive-int     $maxItems
     *
     * @return VectorDocument[]
     */
    private function calculateBatched(array $documents, Vector $vector, int $maxItems): array
    {
        $strategy = $this->resolveStrategy();

        /** @var array<int, array{distance: float, document: VectorDocument}> $candidates */
        $candidates = [];

        foreach (array_chunk($documents, $this->batchSize) as $batch) {
            $batchResults = array_map(
                static fn (VectorDocument $vectorDocument): array => [
                    'distance' => $strategy($vectorDocument, $vector),
                    'document' => $vectorDocument,
                ],
                $batch,
            );

            $candidates = [
                ...$candidates,
                ...$batchResults,
            ];

            usort(
                $candidates,
                static fn (array $a, array $b): int => $a['distance'] <=> $b['distance'],
            );

            if (\count($candidates) > $maxItems) {
                $candidates = \array_slice($candidates, 0, $maxItems);
            }
        }

        return array_map(
            static fn (array $embedding): VectorDocument => $embedding['document']->withScore($embedding['distance']),
            $candidates,
        );
    }

    /**
     * @return \Closure(VectorDocument, Vector): float
     */
    private function resolveStrategy(): \Closure
    {
        return match ($this->strategy) {
            DistanceStrategy::COSINE_DISTANCE => $this->cosineDistance(...),
            DistanceStrategy::ANGULAR_DISTANCE => $this->angularDistance(...),
            DistanceStrategy::EUCLIDEAN_DISTANCE => $this->euclideanDistance(...),
            DistanceStrategy::MANHATTAN_DISTANCE => $this->manhattanDistance(...),
            DistanceStrategy::CHEBYSHEV_DISTANCE => $this->chebyshevDistance(...),
        };
    }

    private function cosineDistance(VectorDocument $embedding, Vector $against): float
    {
        return 1 - $this->cosineSimilarity($embedding, $against);
    }

    private function cosineSimilarity(VectorDocument $embedding, Vector $against): float
    {
        $currentEmbeddingVectors = $embedding->getVector()->getData();

        $dotProduct = array_sum(array: array_map(
            static fn (float $a, float $b): float => $a * $b,
            $currentEmbeddingVectors,
            $against->getData(),
        ));

        $currentEmbeddingLength = sqrt(array_sum(array_map(
            static fn (float $value): float => $value ** 2,
            $currentEmbeddingVectors,
        )));

        $againstLength = sqrt(array_sum(array_map(
            static fn (float $value): float => $value ** 2,
            $against->getData(),
        )));

        return fdiv($dotProduct, $currentEmbeddingLength * $againstLength);
    }

    private function angularDistance(VectorDocument $embedding, Vector $against): float
    {
        $cosineSimilarity = $this->cosineSimilarity($embedding, $against);

        return fdiv(acos($cosineSimilarity), \M_PI);
    }

    private function euclideanDistance(VectorDocument $embedding, Vector $against): float
    {
        return sqrt(array_sum(array_map(
            static fn (float $a, float $b): float => ($a - $b) ** 2,
            $embedding->getVector()->getData(),
            $against->getData(),
        )));
    }

    private function manhattanDistance(VectorDocument $embedding, Vector $against): float
    {
        return array_sum(array_map(
            static fn (float $a, float $b): float => abs($a - $b),
            $embedding->getVector()->getData(),
            $against->getData(),
        ));
    }

    private function chebyshevDistance(VectorDocument $embedding, Vector $against): float
    {
        $embeddingsAsPower = array_map(
            static fn (float $currentValue, float $againstValue): float => abs($currentValue - $againstValue),
            $embedding->getVector()->getData(),
            $against->getData(),
        );

        return array_reduce(
            $embeddingsAsPower,
            static fn (float $value, float $current): float => max($value, $current),
            0.0,
        );
    }
}
