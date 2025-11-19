Supabase Bridge
===============

The Supabase bridge provides vector storage capabilities using `pgvector`_ extension through the REST API.

.. note::

Unlike the Postgres Store, the Supabase Store requires manual setup of the database schema because Supabase doesn't
allow arbitrary SQL execution via REST API.

Requirements
~~~~~~~~~~~~

* Enable `pgvector extension`_ in the relevant schema of your Supabase project for using `vector`_ column types.
* Add columns for embedding (type `vector`) and metadata (type `jsonb`) to your table
* Pre-configured RPC `function`_ for similarity search

See section below for detailed SQL commands.

Database Setup
--------------

Execute the following SQL commands in your Supabase SQL Editor:

Enable ``pgvector`` extension
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

.. code-block:: sql

    CREATE EXTENSION IF NOT EXISTS vector;

Create the `documents` table
~~~~~~~~~~~~~~~~~~~~~~~~~~~~

.. code-block:: sql

    CREATE TABLE IF NOT EXISTS documents (
        id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
        embedding vector(768) NOT NULL,
        metadata JSONB
    );

Create the similarity search function
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

.. code-block:: sql

    CREATE OR REPLACE FUNCTION match_documents(
        query_embedding vector(768),
        match_count int DEFAULT 10,
        match_threshold float DEFAULT 0.0
    )
    RETURNS TABLE (
        id UUID,
        embedding vector,
        metadata JSONB,
        score float
    )
    LANGUAGE sql
    AS $$
        SELECT
            documents.id,
            documents.embedding,
            documents.metadata,
            1- (documents.embedding <=> query_embedding) AS score
        FROM documents
        WHERE 1- (documents.embedding <=> query_embedding) >= match_threshold
        ORDER BY documents.embedding <=> query_embedding ASC
        LIMIT match_count;
    $$;

Create an index for better performance
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

.. code-block:: sql

    CREATE INDEX IF NOT EXISTS documents_embedding_idx
    ON documents USING ivfflat (embedding vector_cosine_ops);

Configuration
-------------

Basic Configuration
~~~~~~~~~~~~~~~~~~~

.. code-block:: php

    use Symfony\AI\Store\Bridge\Supabase\Store;
    use Symfony\Component\HttpClient\HttpClient;

    $store = new Store(
        HttpClient::create(),
        'https://your-project.supabase.co',
        'your-anon-key',
        'documents',        // table name
        'embedding',        // vector field name
        768,               // vector dimension (depending on your embedding model)
        'match_documents'   // function name
    );

Bundle Configuration
~~~~~~~~~~~~~~~~~~~~

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        store:
            supabase:
                my_supabase_store:
                    url: 'https://your-project.supabase.co'
                    api_key: '%env(SUPABASE_API_KEY)%'
                    table: 'documents'
                    vector_field: 'embedding'
                    vector_dimension: 768
                    function_name: 'match_documents'

Environment Variables
~~~~~~~~~~~~~~~~~~~~~

.. code-block:: bash

    # .env.local
    SUPABASE_URL=https://your-project.supabase.co
    SUPABASE_API_KEY=your-supabase-anon-key

Usage
-----

Adding Documents
~~~~~~~~~~~~~~~~

.. code-block:: php

    use Symfony\AI\Platform\Vector\Vector;
    use Symfony\AI\Store\Document\Metadata;
    use Symfony\AI\Store\Document\VectorDocument;
    use Symfony\Component\Uid\Uuid;

    $document = new VectorDocument(
        Uuid::v4(),
        new Vector([0.1, 0.2, 0.3, /* ... 768 dimensions */]),
        new Metadata(['title' => 'My Document', 'category' => 'example'])
    );

    $store->add($document);

Querying Documents
~~~~~~~~~~~~~~~~~~

.. code-block:: php

    $queryVector = new Vector([0.1, 0.2, 0.3, /* ... 768 dimensions */]);

    $results = $store->query($queryVector, [
        'max_items' => 10,
        'min_score' => 0.7
    ]);

    foreach ($results as $document) {
        echo "ID: " . $document->id . "\n";
        echo "Score: " . $document->score . "\n";
        echo "Metadata: " . json_encode($document->metadata->getArrayCopy()) . "\n";
    }

Customization
-------------

You can customize the Supabase setup for different requirements:

Table Name
~~~~~~~~~~

Change ``documents`` to your preferred table name in both the SQL setup and configuration.

Vector Field Name
~~~~~~~~~~~~~~~~~

Change ``embedding`` to your preferred field name in both the SQL setup and configuration.

Vector Dimension
~~~~~~~~~~~~~~~~

Change ``768`` to match your embedding model's dimensions in both the SQL setup and configuration.

Distance Metric
~~~~~~~~~~~~~~~

* Cosine: ``<=>`` (default, recommended for most embeddings)
* Euclidean: ``<->``
* Inner Product: ``<#>``

Index Type
~~~~~~~~~~

* ``ivfflat``: Good balance of speed and accuracy
* ``hnsw``: Better for high-dimensional vectors (requires PostgreSQL 14+)

Limitations
-----------

* Manual schema setup required (no automatic table creation)
* Limited to Supabase's REST API capabilities
* Requires pre-configured RPC functions for complex queries
* Vector dimension must be consistent across all documents

Performance Considerations
--------------------------

* Use appropriate index types based on your vector dimensions
* Consider using ``hnsw`` indexes for high-dimensional vectors
* Batch document insertions when possible (up to 200 documents per request)
* Monitor your Supabase usage limits and quotas

Security Considerations
-----------------------

* Use row-level security (RLS) policies if needed
* Consider using service role keys for server-side operations
* Validate vector dimensions in your application code
* Implement proper error handling for API failures

.. _`pgvector`: https://github.com/pgvector/pgvector
.. _`pgvector extension`: https://supabase.com/docs/guides/database/extensions/pgvector
.. _`vector`: https://supabase.com/docs/guides/ai/vector-columns
.. _`function`: https://supabase.com/docs/guides/database/functions
