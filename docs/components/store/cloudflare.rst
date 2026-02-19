Cloudflare Vectorize Bridge
===========================

The Cloudflare bridge provides vector storage using `Cloudflare Vectorize`_,
a globally distributed vector database integrated with the Cloudflare ecosystem.

Requirements
------------

* Cloudflare account with Vectorize enabled
* Cloudflare API token with Vectorize permissions

Installation
------------

.. code-block:: terminal

    $ composer require symfony/ai-cloudflare-store

Configuration
-------------

.. code-block:: php

    use Symfony\AI\Store\Bridge\Cloudflare\Store;
    use Symfony\Component\HttpClient\HttpClient;

    $store = new Store(
        HttpClient::create(),
        accountId: 'your-cloudflare-account-id',
        apiKey: 'your-cloudflare-api-token',
        index: 'my-index',
        dimensions: 1536,
        metric: 'cosine',
    );

**Available metrics:** ``cosine``, ``euclidean``, ``dotproduct``

Bundle Configuration
~~~~~~~~~~~~~~~~~~~~

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        store:
            cloudflare:
                my_store:
                    account_id: '%env(CLOUDFLARE_ACCOUNT_ID)%'
                    api_key: '%env(CLOUDFLARE_API_KEY)%'
                    index: 'my-index'
                    dimensions: 1536
                    metric: 'cosine'

Environment Variables
~~~~~~~~~~~~~~~~~~~~~

.. code-block:: bash

    CLOUDFLARE_ACCOUNT_ID=your-cloudflare-account-id
    CLOUDFLARE_API_KEY=your-cloudflare-api-token

Index Setup
-----------

The index is created automatically when calling ``setup()``:

.. code-block:: terminal

    $ php bin/console ai:store:setup my_store
    $ php bin/console ai:store:drop my_store

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

.. _`Cloudflare Vectorize`: https://developers.cloudflare.com/vectorize/
