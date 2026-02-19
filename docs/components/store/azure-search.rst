Azure AI Search Bridge
======================

The Azure AI Search bridge provides vector storage using `Azure AI Search`_ (formerly Azure Cognitive Search),
Microsoft's cloud search service with vector similarity support.

Requirements
------------

* Azure subscription with an AI Search resource
* Azure AI Search index pre-configured with a vector field

Installation
------------

.. code-block:: terminal

    $ composer require symfony/ai-azure-search-store

Configuration
-------------

.. code-block:: php

    use Symfony\AI\Store\Bridge\AzureSearch\Store;
    use Symfony\Component\HttpClient\HttpClient;

    $store = new Store(
        HttpClient::create(),
        endpointUrl: 'https://my-search.search.windows.net',
        apiKey: 'your-admin-api-key',
        indexName: 'my-index',
        apiVersion: '2023-11-01',
        vectorFieldName: 'embedding',
    );

Bundle Configuration
~~~~~~~~~~~~~~~~~~~~

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        store:
            azure_search:
                my_store:
                    endpoint: '%env(AZURE_SEARCH_ENDPOINT)%'
                    api_key: '%env(AZURE_SEARCH_API_KEY)%'
                    index: 'my-index'
                    api_version: '2023-11-01'
                    vector_field: 'embedding'

Environment Variables
~~~~~~~~~~~~~~~~~~~~~

.. code-block:: bash

    AZURE_SEARCH_ENDPOINT=https://my-search.search.windows.net
    AZURE_SEARCH_API_KEY=your-admin-api-key

Index Setup
-----------

The Azure AI Search index must be created manually in the `Azure Portal`_ or via the REST API.
The index must include a ``Collection(Edm.Single)`` field with vector search enabled:

.. code-block:: json

    {
        "name": "my-index",
        "fields": [
            {
                "name": "id",
                "type": "Edm.String",
                "key": true
            },
            {
                "name": "embedding",
                "type": "Collection(Edm.Single)",
                "dimensions": 1536,
                "vectorSearchProfile": "my-profile"
            }
        ],
        "vectorSearch": {
            "algorithms": [
                {
                    "name": "my-algorithm",
                    "kind": "hnsw"
                }
            ],
            "profiles": [
                {
                    "name": "my-profile",
                    "algorithm": "my-algorithm"
                }
            ]
        }
    }

.. note::

    Azure AI Search does not support automatic index creation via this bridge.
    The index and vector field configuration must be set up before use.

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

.. note::

    Metadata fields are flattened into document properties. Nested metadata keys are not supported.

.. _`Azure AI Search`: https://azure.microsoft.com/products/ai-services/ai-search
.. _`Azure Portal`: https://portal.azure.com/
