Neo4j Bridge
============

The Neo4j bridge provides vector storage using `Neo4j`_'s vector index,
available since Neo4j 5.11.

Requirements
------------

* Neo4j 5.11+ (Community or Enterprise Edition)

Installation
------------

.. code-block:: terminal

    $ composer require symfony/ai-neo4j-store

Configuration
-------------

.. code-block:: php

    use Symfony\AI\Store\Bridge\Neo4j\Store;
    use Symfony\Component\HttpClient\HttpClient;

    $store = new Store(
        HttpClient::create(),
        endpointUrl: 'http://localhost:7474',
        username: 'neo4j',
        password: 'password',
        databaseName: 'neo4j',
        vectorIndexName: 'document_embeddings',
        nodeName: 'Document',
        embeddingsField: 'embedding',
        embeddingsDimension: 1536,
        embeddingsDistance: 'cosine',
        quantization: false,
    );

**Available distance metrics:** ``cosine``, ``euclidean``

Bundle Configuration
~~~~~~~~~~~~~~~~~~~~

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        store:
            neo4j:
                my_store:
                    endpoint: '%env(NEO4J_URL)%'
                    username: '%env(NEO4J_USERNAME)%'
                    password: '%env(NEO4J_PASSWORD)%'
                    database: 'neo4j'
                    index: 'document_embeddings'
                    node: 'Document'
                    vector_field: 'embedding'
                    dimensions: 1536
                    distance: 'cosine'

Environment Variables
~~~~~~~~~~~~~~~~~~~~~

.. code-block:: bash

    NEO4J_URL=http://localhost:7474
    NEO4J_USERNAME=neo4j
    NEO4J_PASSWORD=password

Index Setup
-----------

The vector index is created automatically when calling ``setup()``:

.. code-block:: terminal

    $ php bin/console ai:store:setup my_store
    $ php bin/console ai:store:drop my_store

Or create the index manually via Cypher:

.. code-block:: cypher

    CREATE VECTOR INDEX document_embeddings IF NOT EXISTS
    FOR (d:Document)
    ON d.embedding
    OPTIONS {
        indexConfig: {
            `vector.dimensions`: 1536,
            `vector.similarity_function`: 'cosine'
        }
    }

Usage
-----

Adding Documents
~~~~~~~~~~~~~~~~

.. code-block:: php

    use Symfony\AI\Store\Document\Metadata;
    use Symfony\AI\Store\Document\VectorDocument;
    use Symfony\AI\Platform\Vector\Vector;
    use Symfony\Component\Uid\Uuid;

    $document = new VectorDocument(
        Uuid::v4(),
        new Vector([0.1, 0.2, /* ... 1536 dimensions */]),
        new Metadata(['source' => 'my-document.txt'])
    );

    $store->add($document);

Querying Documents
~~~~~~~~~~~~~~~~~~

.. code-block:: php

    use Symfony\AI\Store\Query\VectorQuery;

    $results = $store->query(new VectorQuery(new Vector([0.1, 0.2, /* ... */]), maxResults: 10));

    foreach ($results as $document) {
        echo $document->metadata['source'];
    }

Setup with Docker
-----------------

.. code-block:: terminal

    $ docker run -p 7474:7474 -p 7687:7687 \
        -e NEO4J_AUTH=neo4j/password \
        neo4j:latest

.. _`Neo4j`: https://neo4j.com/
