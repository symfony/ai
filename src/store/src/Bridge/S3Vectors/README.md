# AWS S3 Vectors Store Bridge for Symfony AI

This bridge provides integration between Symfony AI Store and AWS S3 Vectors for vector storage and similarity search.

## Installation

```bash
composer require symfony/ai-s3vectors-store
```

## Configuration

```php
use AsyncAws\S3Vectors\S3VectorsClient;
use Symfony\AI\Store\Bridge\S3Vectors\Store;

$client = new S3VectorsClient([
    'region' => 'us-east-1',
]);

$store = new Store(
    client: $client,
    vectorBucketName: 'my-vector-bucket',
    indexName: 'my-index',
    filter: [], // Optional: default filter for queries
    topK: 3, // Optional: default number of results
);

// Setup the vector bucket and index
$store->setup([
    'dimension' => 1536,
    'distanceMetric' => \AsyncAws\S3Vectors\Enum\DistanceMetric::COSINE, // Optional
    'dataType' => \AsyncAws\S3Vectors\Enum\DataType::FLOAT32, // Optional
    'encryption' => ['kmsKeyId' => 'your-kms-key-id'], // Optional
    'tags' => ['env' => 'production'], // Optional
]);
```

## Usage

```php
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\Component\Uid\Uuid;

// Add documents
$document = new VectorDocument(
    id: Uuid::v4(),
    vector: new Vector([0.1, 0.2, 0.3, ...]),
    metadata: new Metadata(['title' => 'My Document'])
);
$store->add($document);

// Query similar vectors
$results = $store->query(
    vector: new Vector([0.1, 0.2, 0.3, ...]),
    options: [
        'topK' => 5,
        'filter' => ['category' => 'documentation'],
    ]
);

foreach ($results as $result) {
    echo $result->metadata['title'] . ' (score: ' . $result->score . ')' . PHP_EOL;
}

// Remove documents
$store->remove(['id1', 'id2']);

// Drop the index and bucket
$store->drop();
```

## Features

- Full CRUD operations for vector documents
- Similarity search with configurable distance metrics (cosine, euclidean)
- Metadata filtering support
- KMS encryption support
- Tag management
- Batch operations

## Resources

- [AWS S3 Vectors Documentation](https://docs.aws.amazon.com/AmazonS3/latest/userguide/s3-vectors.html)
- [AsyncAws S3Vectors Package](https://github.com/async-aws/aws/tree/master/src/Service/S3Vectors)
- [Symfony AI Documentation](https://github.com/symfony/ai)
