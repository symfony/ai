SQLite Store
============

The SQLite store provides a lightweight persistent vector store without external dependencies.
It uses SQLite for data persistence and FTS5 for full-text search capabilities.

.. note::

    The ``SQLite`` store loads all vectors into PHP memory for distance calculation during vector queries.
    The dataset must fit within PHP's memory limit.

Installation
------------

.. code-block:: terminal

    $ composer require symfony/ai-sqlite-store

Basic Usage
-----------

Using a file-based SQLite database for persistence::

    use Symfony\AI\Store\Bridge\Sqlite\Store;

    $pdo = new \PDO('sqlite:/path/to/vectors.db');
    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

    $store = new Store($pdo, 'my_vectors');
    $store->setup();

Using an in-memory SQLite database (for testing)::

    $pdo = new \PDO('sqlite::memory:');
    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

    $store = new Store($pdo, 'my_vectors');
    $store->setup();

Factory Methods
---------------

Create from a PDO connection::

    $store = Store::fromPdo($pdo, 'my_vectors');

Create from a Doctrine DBAL connection::

    $store = Store::fromDbal($dbalConnection, 'my_vectors');

Distance Strategies
-------------------

The SQLite store supports different distance calculation strategies::

    use Symfony\AI\Store\Bridge\Sqlite\Store;
    use Symfony\AI\Store\Distance\DistanceCalculator;
    use Symfony\AI\Store\Distance\DistanceStrategy;

    $calculator = new DistanceCalculator(DistanceStrategy::COSINE_DISTANCE);
    $store = new Store($pdo, 'my_vectors', $calculator);

Available strategies:

* ``COSINE_DISTANCE`` (default)
* ``EUCLIDEAN_DISTANCE``
* ``MANHATTAN_DISTANCE``
* ``ANGULAR_DISTANCE``
* ``CHEBYSHEV_DISTANCE``

Text Search
-----------

The SQLite store uses FTS5 for full-text search. Documents with ``_text`` metadata
are automatically indexed for text search::

    use Symfony\AI\Store\Query\TextQuery;

    $results = $store->query(new TextQuery('artificial intelligence'));

Hybrid queries combine vector similarity and text search::

    use Symfony\AI\Store\Query\HybridQuery;

    $results = $store->query(
        new HybridQuery($vector, 'search terms', 0.5)
    );

Metadata Filtering
------------------

The SQLite store supports filtering search results based on document metadata using a callable::

    use Symfony\AI\Store\Document\VectorDocument;

    $results = $store->query($vectorQuery, [
        'filter' => fn(VectorDocument $doc) => $doc->getMetadata()['category'] === 'products',
    ]);

Query Options
-------------

The SQLite store supports the following query options:

* ``maxItems`` (int) - Limit the number of results returned
* ``filter`` (callable) - Filter documents by metadata before distance calculation

Example combining both options::

    $results = $store->query($vectorQuery, [
        'maxItems' => 5,
        'filter' => fn(VectorDocument $doc) => $doc->getMetadata()['active'] === true,
    ]);
