<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\S3Vectors;

use AsyncAws\S3Vectors\Enum\DataType;
use AsyncAws\S3Vectors\Enum\DistanceMetric;
use AsyncAws\S3Vectors\S3VectorsClient;
use AsyncAws\S3Vectors\ValueObject\PutInputVector;
use AsyncAws\S3Vectors\ValueObject\VectorDataMemberFloat32;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\ManagedStoreInterface;
use Symfony\AI\Store\StoreInterface;

/**
 * AWS S3 Vectors store implementation using AsyncAws.
 *
 * @author AUH Nahvi <aszenz@gmail.com>
 */
final class Store implements ManagedStoreInterface, StoreInterface
{
    /**
     * @param array<string, mixed> $filter
     */
    public function __construct(
        private readonly S3VectorsClient $client,
        private readonly string $vectorBucketName,
        private readonly string $indexName,
        private readonly array $filter = [],
        private readonly int $topK = 3,
    ) {
    }

    /**
     * @param array{
     *     dimension?: int,
     *     distanceMetric?: DistanceMetric::*,
     *     dataType?: DataType::*,
     *     metadata?: array<string, array<string, mixed>>,
     *     encryption?: array{kmsKeyId?: string},
     *     tags?: array<string, string>,
     * } $options
     */
    public function setup(array $options = []): void
    {
        if (!isset($options['dimension'])) {
            throw new InvalidArgumentException('The "dimension" option is required.');
        }

        // Create vector bucket if it doesn't exist
        try {
            $this->client->getVectorBucket([
                'vectorBucketName' => $this->vectorBucketName,
            ]);
        } catch (\Exception) {
            $bucketInput = [
                'vectorBucketName' => $this->vectorBucketName,
            ];

            if (isset($options['encryption']['kmsKeyId'])) {
                $bucketInput['encryptionConfiguration'] = [
                    'kmsKeyId' => $options['encryption']['kmsKeyId'],
                ];
            }

            if (isset($options['tags'])) {
                $bucketInput['tags'] = $options['tags'];
            }

            $this->client->createVectorBucket($bucketInput);
        }

        // Create index
        $indexInput = [
            'vectorBucketName' => $this->vectorBucketName,
            'indexName' => $this->indexName,
            'dimension' => $options['dimension'],
            'distanceMetric' => $options['distanceMetric'] ?? DistanceMetric::COSINE,
            'dataType' => $options['dataType'] ?? DataType::FLOAT_32,
        ];

        if (isset($options['metadata'])) {
            $indexInput['metadataConfiguration'] = $options['metadata'];
        }

        if (isset($options['encryption']['kmsKeyId'])) {
            $indexInput['encryptionConfiguration'] = [
                'kmsKeyId' => $options['encryption']['kmsKeyId'],
            ];
        }

        if (isset($options['tags'])) {
            $indexInput['tags'] = $options['tags'];
        }

        $this->client->createIndex($indexInput);
    }

    public function add(VectorDocument|array $documents): void
    {
        if ($documents instanceof VectorDocument) {
            $documents = [$documents];
        }

        if ([] === $documents) {
            return;
        }

        $vectors = [];
        foreach ($documents as $document) {
            $vector = [
                'key' => (string) $document->id,
                'data' => new VectorDataMemberFloat32(['float32' => $document->vector->getData()]),
            ];

            if ([] !== $document->metadata->getArrayCopy()) {
                $vector['metadata'] = $document->metadata->getArrayCopy();
            }

            $vectors[] = PutInputVector::create($vector);
        }

        $this->client->putVectors([
            'vectorBucketName' => $this->vectorBucketName,
            'indexName' => $this->indexName,
            'vectors' => $vectors,
        ]);
    }

    /**
     * @param string|array<string> $ids
     * @param array{
     *     filter?: array<string, mixed>,
     * } $options
     */
    public function remove(string|array $ids, array $options = []): void
    {
        if (\is_string($ids)) {
            $ids = [$ids];
        }

        if ([] === $ids) {
            return;
        }

        $deleteInput = [
            'vectorBucketName' => $this->vectorBucketName,
            'indexName' => $this->indexName,
            'keys' => $ids,
        ];

        if (isset($options['filter'])) {
            $deleteInput['filter'] = $options['filter'];
        }

        $this->client->deleteVectors($deleteInput);
    }

    /**
     * @param array{
     *     filter?: array<string, mixed>,
     *     topK?: int,
     *     returnMetadata?: bool,
     *     returnDistance?: bool,
     * } $options
     */
    public function query(Vector $vector, array $options = []): iterable
    {
        $result = $this->client->queryVectors([
            'vectorBucketName' => $this->vectorBucketName,
            'indexName' => $this->indexName,
            'queryVector' => new VectorDataMemberFloat32(['float32' => $vector->getData()]),
            'topK' => $options['topK'] ?? $this->topK,
            'filter' => $options['filter'] ?? $this->filter,
            'returnMetadata' => $options['returnMetadata'] ?? true,
            'returnDistance' => $options['returnDistance'] ?? true,
        ]);

        foreach ($result->getVectors() as $outputVector) {
            $metadata = $outputVector->getMetadata();

            /** @var array<string, mixed> $metadataArray */
            $metadataArray = \is_array($metadata) ? $metadata : [];

            yield new VectorDocument(
                id: $outputVector->getKey(),
                vector: $vector,
                metadata: new Metadata($metadataArray),
                score: $outputVector->getDistance(),
            );
        }
    }

    public function drop(array $options = []): void
    {
        // Delete index first
        $this->client->deleteIndex([
            'vectorBucketName' => $this->vectorBucketName,
            'indexName' => $this->indexName,
        ]);

        // Then delete the bucket
        $this->client->deleteVectorBucket([
            'vectorBucketName' => $this->vectorBucketName,
        ]);
    }
}
