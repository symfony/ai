AWS S3 Vectors Bridge
=====================

The AWS S3 Vectors bridge provides vector storage and similarity search integration using `AWS S3 Vectors`_.

Requirements
~~~~~~~~~~~~

* An AWS account with access to S3 Vectors
* The AsyncAws S3Vectors client

Installation
~~~~~~~~~~~~

Install the bridge package:

.. code-block:: terminal

    $ composer require symfony/ai-s3vectors-store

Configuration
-------------

Basic Configuration
~~~~~~~~~~~~~~~~~~~

::

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

Setup the Vector Store
~~~~~~~~~~~~~~~~~~~~~~

Before using the store, you need to initialize it with the appropriate configuration::

    // Setup the vector bucket and index
    $store->setup([
        'dimension' => 1536,
        'distanceMetric' => \AsyncAws\S3Vectors\Enum\DistanceMetric::COSINE, // Optional
        'dataType' => \AsyncAws\S3Vectors\Enum\DataType::FLOAT32, // Optional
        'encryption' => ['kmsKeyId' => 'your-kms-key-id'], // Optional
        'tags' => ['env' => 'production'], // Optional
    ]);

Usage
-----

Add Documents
~~~~~~~~~~~~~

::

    use Symfony\AI\Platform\Vector\Vector;
    use Symfony\AI\Store\Document\Metadata;
    use Symfony\AI\Store\Document\VectorDocument;
    use Symfony\Component\Uid\Uuid;

    $document = new VectorDocument(
        id: Uuid::v4(),
        vector: new Vector([0.1, 0.2, 0.3, ...]),
        metadata: new Metadata(['title' => 'My Document'])
    );
    $store->add($document);

Query Similar Vectors
~~~~~~~~~~~~~~~~~~~~~

::

    use Symfony\AI\Platform\Vector\Vector;

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

Remove Documents
~~~~~~~~~~~~~~~~

::

    $store->remove(['id1', 'id2']);

Drop the Store
~~~~~~~~~~~~~~

::

    // Drop the index and bucket
    $store->drop();

Features
--------

* Full CRUD operations for vector documents
* Similarity search with configurable distance metrics (cosine, euclidean)
* Metadata filtering support
* KMS encryption support
* Tag management
* Batch operations

Distance Metrics
~~~~~~~~~~~~~~~~

The bridge supports the following distance metrics:

* ``COSINE`` - Cosine distance (default)
* ``EUCLIDEAN`` - Euclidean distance
* ``DOT_PRODUCT`` - Dot product distance

Data Types
~~~~~~~~~~

The bridge supports the following data types for vectors:

* ``FLOAT32`` - 32-bit floating point (default)

.. _`AWS S3 Vectors`: https://docs.aws.amazon.com/AmazonS3/latest/userguide/s3-vectors.html
