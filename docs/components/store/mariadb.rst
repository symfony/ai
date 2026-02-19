MariaDB Bridge
==============

The MariaDB bridge provides vector storage using MariaDB's native `VECTOR`_ column type,
available since MariaDB 11.7.

Requirements
------------

* MariaDB 11.7+
* PHP ``ext-pdo`` extension

Installation
------------

.. code-block:: terminal

    $ composer require symfony/ai-maria-db-store

Database Setup
--------------

Create the table with a ``VECTOR`` column:

.. code-block:: sql

    CREATE TABLE IF NOT EXISTS documents (
        id CHAR(36) PRIMARY KEY,
        embedding VECTOR(1536) NOT NULL,
        metadata JSON
    );

    -- Recommended index for performance
    CREATE VECTOR INDEX ON documents (embedding);

Adjust ``1536`` to match your embedding model's dimensions.

Configuration
-------------

.. code-block:: php

    use Symfony\AI\Store\Bridge\MariaDb\Store;

    // From a PDO connection
    $pdo = new \PDO('mysql:host=localhost;dbname=mydb', $user, $password);
    $store = Store::fromPdo(
        $pdo,
        tableName: 'documents',
        indexName: 'embedding_idx',
        vectorFieldName: 'embedding',
    );

    // Or from a Doctrine DBAL connection
    $store = Store::fromDbal(
        $connection,
        tableName: 'documents',
        indexName: 'embedding_idx',
        vectorFieldName: 'embedding',
    );

Bundle Configuration
~~~~~~~~~~~~~~~~~~~~

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        store:
            mariadb:
                my_store:
                    dsn: '%env(DATABASE_URL)%'
                    table: 'documents'
                    index: 'embedding_idx'
                    vector_field: 'embedding'

Environment Variables
~~~~~~~~~~~~~~~~~~~~~

.. code-block:: bash

    DATABASE_URL=mysql://user:password@localhost:3306/mydb

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

Using ``ai:store:setup``
------------------------

When using the AI Bundle, the store can be initialized automatically:

.. code-block:: terminal

    $ php bin/console ai:store:setup my_store
    $ php bin/console ai:store:drop my_store

.. _`VECTOR`: https://mariadb.com/kb/en/vector-overview/
