Local Stores (InMemory & Cache)
===============================

The local stores provide in-memory vector storage without external dependencies.

.. note::

    Both ``InMemoryStore`` and ``CacheStore`` load all data into PHP memory during queries.
    The dataset must fit within PHP's memory limit.

InMemoryStore
-------------

Stores vectors in a PHP array. Data is not persisted and is lost when the PHP process ends::

    use Symfony\AI\Store\Bridge\Local\InMemoryStore;

    $store = new InMemoryStore();
    $store->add($document1, $document2);
    $results = $store->query($vector);

CacheStore
----------

Stores vectors using a PSR-6 cache implementation. Persistence depends on the cache adapter used::

    use Symfony\AI\Store\Bridge\Local\CacheStore;
    use Symfony\Component\Cache\Adapter\FilesystemAdapter;

    $cache = new FilesystemAdapter();
    $store = new CacheStore($cache);
    $store->add($document1, $document2);
    $results = $store->query($vector);

Distance Strategies
-------------------

Both stores support different distance calculation strategies::

    use Symfony\AI\Store\Distance\DistanceCalculator;
    use Symfony\AI\Store\Distance\DistanceStrategy;

    $calculator = new DistanceCalculator(DistanceStrategy::COSINE_DISTANCE);
    $store = new InMemoryStore($calculator);

Available strategies:

* ``COSINE_DISTANCE`` (default)
* ``EUCLIDEAN_DISTANCE``
* ``MANHATTAN_DISTANCE``
* ``ANGULAR_DISTANCE``
* ``CHEBYSHEV_DISTANCE``

Metadata Filtering
------------------

Both stores support filtering search results based on document metadata using a callable::

    use Symfony\AI\Store\Document\VectorDocument;

    $results = $store->query($vector, [
        'filter' => fn(VectorDocument $doc) => $doc->metadata['category'] === 'products',
    ]);

You can combine multiple conditions::

    $results = $store->query($vector, [
        'filter' => fn(VectorDocument $doc) =>
            $doc->metadata['price'] <= 100
            && $doc->metadata['stock'] > 0
            && $doc->metadata['enabled'] === true,
        'maxItems' => 10,
    ]);

Filter nested metadata::

    $results = $store->query($vector, [
        'filter' => fn(VectorDocument $doc) =>
            $doc->metadata['options']['size'] === 'S'
            && $doc->metadata['options']['color'] === 'blue',
    ]);

Use array functions for complex filtering::

    $allowedBrands = ['Nike', 'Adidas', 'Puma'];
    $results = $store->query($vector, [
        'filter' => fn(VectorDocument $doc) =>
            \in_array($doc->metadata['brand'] ?? '', $allowedBrands, true),
    ]);

.. note::

    Filtering is applied before distance calculation.

Query Options
-------------

Both stores support the following query options:

* ``maxItems`` (int) - Limit the number of results returned
* ``filter`` (callable) - Filter documents by metadata before distance calculation

Example combining both options::

    $results = $store->query($vector, [
        'maxItems' => 5,
        'filter' => fn(VectorDocument $doc) => $doc->metadata['active'] === true,
    ]);
