Local Stores (InMemory & Cache)
===============================

The local stores provide in-memory vector storage without external dependencies.

.. note::

    Both the InMemory store and the Cache store load all data into PHP memory during queries.
    The dataset must fit within PHP's memory limit.

InMemory Store
--------------

Stores vectors in a PHP array. Data is not persisted and is lost when the PHP process ends::

    use Symfony\AI\Store\InMemory\Store;
    use Symfony\AI\Store\Query\VectorQuery;

    $store = new Store();
    $store->add([$document1, $document2]);
    $results = $store->query(new VectorQuery($vector));

Cache Store
-----------

Stores vectors using a PSR-6 cache implementation. Persistence depends on the cache adapter used::

    use Symfony\AI\Store\Bridge\Cache\Store;
    use Symfony\AI\Store\Query\VectorQuery;
    use Symfony\Component\Cache\Adapter\FilesystemAdapter;

    $cache = new FilesystemAdapter();
    $store = new Store($cache);
    $store->add([$document1, $document2]);
    $results = $store->query(new VectorQuery($vector));

Distance Strategies
-------------------

Both stores support different distance calculation strategies::

    use Symfony\AI\Store\Distance\DistanceCalculator;
    use Symfony\AI\Store\Distance\DistanceStrategy;
    use Symfony\AI\Store\InMemory\Store;

    $calculator = new DistanceCalculator(DistanceStrategy::COSINE_DISTANCE);
    $store = new Store($calculator);

Available strategies:

* ``COSINE_DISTANCE`` (default)
* ``EUCLIDEAN_DISTANCE``
* ``MANHATTAN_DISTANCE``
* ``ANGULAR_DISTANCE``
* ``CHEBYSHEV_DISTANCE``

Batch Processing
----------------

For large datasets, the distance calculator can process documents in batches
instead of scoring the entire dataset at once. After each batch, only the best
candidates are kept, reducing peak memory from O(N) to O(limit + batchSize)::

    use Symfony\AI\Store\Distance\DistanceCalculator;
    use Symfony\AI\Store\Distance\DistanceStrategy;
    use Symfony\AI\Store\InMemory\Store;

    $calculator = new DistanceCalculator(
        strategy: DistanceStrategy::COSINE_DISTANCE,
        batchSize: 500, # Default to 100
    );
    $store = new Store($calculator);

    // Batch processing is activated when both batchSize and limit are set
    $results = $store->query($vectorQuery, [
        'limit' => 10,
    ]);

.. note::

    Batch processing requires ``limit`` to be set in the query options.
    Without it, the calculator falls back to the standard full-sort behavior
    since all results are needed and no pruning can occur.

Metadata Filtering
------------------

Both stores support filtering search results based on document metadata using a callable::

    use Symfony\AI\Store\Document\VectorDocumentInterface;

    $results = $store->query($vectorQuery, [
        'filter' => fn(VectorDocumentInterface $doc) => $doc->getMetadata()['category'] === 'products',
    ]);

You can combine multiple conditions::

    $results = $store->query($vectorQuery, [
        'filter' => fn(VectorDocumentInterface $doc) =>
            $doc->getMetadata()['price'] <= 100
            && $doc->getMetadata()['stock'] > 0
            && $doc->getMetadata()['enabled'] === true,
        'limit' => 10,
    ]);

Filter nested metadata::

    $results = $store->query($vectorQuery, [
        'filter' => fn(VectorDocumentInterface $doc) =>
            $doc->getMetadata()['options']['size'] === 'S'
            && $doc->getMetadata()['options']['color'] === 'blue',
    ]);

Use array functions for complex filtering::

    $allowedBrands = ['Nike', 'Adidas', 'Puma'];
    $results = $store->query($vectorQuery, [
        'filter' => fn(VectorDocumentInterface $doc) =>
            \in_array($doc->getMetadata()['brand'] ?? '', $allowedBrands, true),
    ]);

.. note::

    Filtering is applied before distance calculation.

Query Options
-------------

Both stores support the following query options:

* ``limit`` (int) - Limit the number of results returned
* ``filter`` (callable) - Filter documents by metadata before distance calculation

Example combining both options::

    $results = $store->query($vectorQuery, [
        'limit' => 5,
        'filter' => fn(VectorDocumentInterface $doc) => $doc->getMetadata()['active'] === true,
    ]);
