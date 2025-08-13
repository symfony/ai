Store Component
===============

The Store component provides a unified abstraction for vector storage, enabling semantic search and 
Retrieval Augmented Generation (RAG) patterns. It supports multiple vector databases while maintaining 
a consistent interface.

Overview
--------

The Store component enables you to:

* Store and retrieve vector embeddings efficiently
* Implement semantic search across documents
* Build RAG systems for context-aware AI responses
* Work with multiple vector database backends
* Transform and split documents for optimal indexing
* Calculate similarity between vectors using various strategies

Basic Usage
-----------

Creating a Store
~~~~~~~~~~~~~~~~

.. code-block:: php

    use Symfony\AI\Store\InMemoryStore;
    use Symfony\AI\Store\Indexer;
    use Symfony\AI\Store\Document\TextDocument;
    use Symfony\AI\Platform\Bridge\OpenAi\Embeddings;

    // Create a store (in-memory for this example)
    $store = new InMemoryStore();

    // Create embeddings model
    $embeddings = new Embeddings(Embeddings::TEXT_3_SMALL);

    // Create indexer
    $indexer = new Indexer($platform, $embeddings, $store);

    // Index documents
    $document = new TextDocument('Paris is the capital of France.');
    $indexer->index($document);

Querying the Store
~~~~~~~~~~~~~~~~~~

.. code-block:: php

    use Symfony\AI\Platform\Vector\Vector;

    // Create query embedding
    $queryResult = $platform->invoke($embeddings, 'What is the capital of France?');
    $queryVector = $queryResult->asVectors()[0];

    // Search for similar documents
    $results = $store->query($queryVector, [
        'limit' => 5,           // Maximum results
        'threshold' => 0.7      // Minimum similarity score
    ]);

    foreach ($results as $result) {
        echo $result->document->getContent(); // "Paris is the capital of France."
        echo $result->score;                  // Similarity score (0-1)
    }

Document Management
-------------------

Text Documents
~~~~~~~~~~~~~~

Create and manage text documents with metadata:

.. code-block:: php

    use Symfony\AI\Store\Document\TextDocument;
    use Symfony\AI\Store\Document\Metadata;

    // Simple document
    $document = new TextDocument('Content here');

    // Document with metadata
    $document = new TextDocument(
        content: 'Product description for iPhone 15',
        metadata: new Metadata([
            'source' => 'product_catalog',
            'category' => 'electronics',
            'product_id' => 'iphone-15',
            'updated_at' => '2024-01-15'
        ])
    );

    // Access metadata
    $metadata = $document->getMetadata();
    echo $metadata->get('category'); // 'electronics'

Vector Documents
~~~~~~~~~~~~~~~~

Work directly with pre-computed vectors:

.. code-block:: php

    use Symfony\AI\Store\Document\VectorDocument;
    use Symfony\AI\Platform\Vector\Vector;

    // Create vector document
    $vector = new Vector([0.1, 0.2, 0.3, 0.4, 0.5]);
    $document = new VectorDocument(
        vector: $vector,
        content: 'Original text content',
        metadata: new Metadata(['source' => 'manual'])
    );

    // Add to store
    $store->add($document);

Document Transformation
-----------------------

Text Splitting
~~~~~~~~~~~~~~

Split large documents into manageable chunks:

.. code-block:: php

    use Symfony\AI\Store\Document\Transformer\TextSplitTransformer;

    $transformer = new TextSplitTransformer(
        maxLength: 500,        // Maximum chunk size
        overlap: 50,           // Overlap between chunks
        separator: "\n\n"      // Split on paragraphs
    );

    $longDocument = new TextDocument($longText);
    $chunks = $transformer->transform($longDocument);

    // Index each chunk
    foreach ($chunks as $chunk) {
        $indexer->index($chunk);
    }

Chain Transformers
~~~~~~~~~~~~~~~~~~

Combine multiple transformers:

.. code-block:: php

    use Symfony\AI\Store\Document\Transformer\ChainTransformer;
    use Symfony\AI\Store\Document\Transformer\ChunkDelayTransformer;

    $chainTransformer = new ChainTransformer([
        new TextSplitTransformer(maxLength: 1000),
        new ChunkDelayTransformer(delay: 100) // Rate limiting
    ]);

    $documents = $chainTransformer->transform($document);

Document Loading
~~~~~~~~~~~~~~~~

Load documents from files:

.. code-block:: php

    use Symfony\AI\Store\Document\Loader\TextFileLoader;

    $loader = new TextFileLoader();
    $document = $loader->load('/path/to/document.txt');

    // With metadata extraction
    $document = $loader->load('/path/to/document.txt', [
        'extract_metadata' => true  // Extract file metadata
    ]);

Vector Stores
-------------

In-Memory Store
~~~~~~~~~~~~~~~

For development and testing:

.. code-block:: php

    use Symfony\AI\Store\InMemoryStore;

    $store = new InMemoryStore();
    
    // Supports all standard operations
    $store->add($vectorDocument);
    $results = $store->query($queryVector);

MariaDB Store
~~~~~~~~~~~~~

For production with MariaDB:

.. code-block:: php

    use Symfony\AI\Store\Bridge\MariaDb\Store;

    $pdo = new \PDO('mysql:host=localhost;dbname=vectors', 'user', 'pass');
    $store = new Store(
        connection: $pdo,
        tableName: 'embeddings',
        vectorDimensions: 1536
    );

    // Initialize table structure
    if ($store instanceof InitializableStoreInterface) {
        $store->initialize();
    }

MongoDB Store
~~~~~~~~~~~~~

For MongoDB Atlas with vector search:

.. code-block:: php

    use Symfony\AI\Store\Bridge\MongoDb\Store;
    use MongoDB\Client;

    $client = new Client('mongodb://localhost:27017');
    $store = new Store(
        collection: $client->selectCollection('ai', 'vectors'),
        indexName: 'vector_index'
    );

Pinecone Store
~~~~~~~~~~~~~~

For managed vector database:

.. code-block:: php

    use Symfony\AI\Store\Bridge\Pinecone\Store;
    use Pinecone\Client;

    $client = new Client($_ENV['PINECONE_API_KEY']);
    $store = new Store(
        client: $client,
        indexName: 'my-index',
        namespace: 'production'
    );

PostgreSQL Store
~~~~~~~~~~~~~~~~

With pgvector extension:

.. code-block:: php

    use Symfony\AI\Store\Bridge\Postgres\Store;
    use Symfony\AI\Store\Bridge\Postgres\Distance;

    $pdo = new \PDO('pgsql:host=localhost;dbname=vectors', 'user', 'pass');
    $store = new Store(
        connection: $pdo,
        tableName: 'embeddings',
        vectorDimensions: 1536,
        distanceStrategy: Distance::COSINE
    );

Cache Store
~~~~~~~~~~~

With PSR-6 cache:

.. code-block:: php

    use Symfony\AI\Store\CacheStore;
    use Symfony\Component\Cache\Adapter\FilesystemAdapter;

    $cache = new FilesystemAdapter();
    $store = new CacheStore($cache);

Distance Strategies
-------------------

Configure how similarity is calculated:

.. code-block:: php

    use Symfony\AI\Store\DistanceStrategy;
    use Symfony\AI\Store\DistanceCalculator;

    // Available strategies
    $strategies = [
        DistanceStrategy::COSINE,      // Cosine similarity (default)
        DistanceStrategy::EUCLIDEAN,   // Euclidean distance
        DistanceStrategy::DOT_PRODUCT  // Dot product
    ];

    // Manual calculation
    $calculator = new DistanceCalculator();
    $similarity = $calculator->calculate(
        $vector1,
        $vector2,
        DistanceStrategy::COSINE
    );

Indexing Strategies
-------------------

Batch Indexing
~~~~~~~~~~~~~~

Index multiple documents efficiently:

.. code-block:: php

    $documents = [
        new TextDocument('First document'),
        new TextDocument('Second document'),
        new TextDocument('Third document')
    ];

    // Batch index
    foreach ($documents as $document) {
        $indexer->index($document);
    }

    // Or with vector documents
    $vectorDocuments = array_map(
        fn($doc) => $indexer->vectorize($doc),
        $documents
    );
    $store->add(...$vectorDocuments);

Incremental Indexing
~~~~~~~~~~~~~~~~~~~~

Add documents over time:

.. code-block:: php

    class DocumentProcessor
    {
        public function __construct(
            private Indexer $indexer,
            private LoggerInterface $logger
        ) {}

        public function processNewDocuments(array $documents): void
        {
            foreach ($documents as $document) {
                try {
                    $this->indexer->index($document);
                    $this->logger->info('Indexed document', [
                        'content' => substr($document->getContent(), 0, 50)
                    ]);
                } catch (\Exception $e) {
                    $this->logger->error('Indexing failed', [
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
    }

RAG Implementation
------------------

Basic RAG Pattern
~~~~~~~~~~~~~~~~~

.. code-block:: php

    use Symfony\AI\Agent\Toolbox\Tool\SimilaritySearch;
    use Symfony\AI\Agent\Toolbox\Toolbox;
    use Symfony\AI\Agent\Toolbox\AgentProcessor;

    // Create similarity search tool
    $similaritySearch = new SimilaritySearch($embeddings, $store);

    // Create agent with RAG
    $toolbox = Toolbox::create($similaritySearch);
    $processor = new AgentProcessor($toolbox);

    $agent = new Agent($platform, $model, [$processor], [$processor]);

    // Use RAG
    $messages = new MessageBag(
        Message::forSystem(
            'Answer questions using only the similarity_search tool. ' .
            'If you cannot find relevant information, say so.'
        ),
        Message::ofUser('What products do we sell?')
    );

    $result = $agent->call($messages);

Advanced RAG with Metadata
~~~~~~~~~~~~~~~~~~~~~~~~~~

Filter results based on metadata:

.. code-block:: php

    class MetadataFilteredSearch extends SimilaritySearch
    {
        public function __invoke(
            string $query,
            int $limit = 5,
            ?string $category = null
        ): array {
            // Get base results
            $results = parent::__invoke($query, $limit * 2);
            
            // Filter by metadata
            if ($category) {
                $results = array_filter(
                    $results,
                    fn($r) => $r['metadata']['category'] === $category
                );
            }
            
            return array_slice($results, 0, $limit);
        }
    }

Store Configuration
-------------------

Symfony Bundle Configuration
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        store:
            mariadb:
                default:
                    dsn: '%env(DATABASE_URL)%'
                    table: 'vectors'
                    dimensions: 1536
            
            mongodb:
                default:
                    connection: '%env(MONGODB_URL)%'
                    collection: 'embeddings'
                    index: 'vector_index'
            
            pinecone:
                default:
                    api_key: '%env(PINECONE_API_KEY)%'
                    index: 'production'
                    namespace: 'default'

Service Injection
~~~~~~~~~~~~~~~~~

.. code-block:: php

    namespace App\Service;

    use Symfony\AI\Store\StoreInterface;
    use Symfony\AI\Store\Indexer;

    class SearchService
    {
        public function __construct(
            private StoreInterface $store,
            private Indexer $indexer
        ) {}

        public function indexDocument(string $content): void
        {
            $document = new TextDocument($content);
            $this->indexer->index($document);
        }

        public function search(string $query): array
        {
            // Implementation
        }
    }

Performance Optimization
------------------------

Caching Embeddings
~~~~~~~~~~~~~~~~~~

Cache computed embeddings to avoid recomputation:

.. code-block:: php

    use Psr\Cache\CacheItemPoolInterface;

    class CachedIndexer
    {
        public function __construct(
            private Indexer $indexer,
            private CacheItemPoolInterface $cache
        ) {}

        public function index(TextDocument $document): void
        {
            $key = 'embedding_' . md5($document->getContent());
            $item = $this->cache->getItem($key);
            
            if (!$item->isHit()) {
                $this->indexer->index($document);
                $item->set($document);
                $this->cache->save($item);
            }
        }
    }

Batch Processing
~~~~~~~~~~~~~~~~

Process documents in batches for efficiency:

.. code-block:: php

    class BatchIndexer
    {
        private array $batch = [];
        private int $batchSize = 100;

        public function add(TextDocument $document): void
        {
            $this->batch[] = $document;
            
            if (count($this->batch) >= $this->batchSize) {
                $this->flush();
            }
        }

        public function flush(): void
        {
            if (empty($this->batch)) {
                return;
            }

            // Process batch
            foreach ($this->batch as $document) {
                $this->indexer->index($document);
            }
            
            $this->batch = [];
        }
    }

Testing
-------

Test with in-memory store:

.. code-block:: php

    use Symfony\AI\Store\InMemoryStore;
    use Symfony\AI\Platform\InMemoryPlatform;

    class RAGTest extends TestCase
    {
        public function testSemanticSearch(): void
        {
            // Setup
            $store = new InMemoryStore();
            $platform = new InMemoryPlatform(
                fn() => new VectorResult(new Vector([0.1, 0.2, 0.3]))
            );
            
            // Add test documents
            $doc = new VectorDocument(
                new Vector([0.1, 0.2, 0.3]),
                'Test content'
            );
            $store->add($doc);
            
            // Test query
            $results = $store->query(new Vector([0.1, 0.2, 0.3]));
            
            $this->assertCount(1, $results);
            $this->assertEquals('Test content', $results[0]->document->getContent());
        }
    }

Next Steps
----------

* Implement RAG: :doc:`../features/rag`
* Configure stores: :doc:`../stores/overview`
* Build semantic search: :doc:`../guides/semantic-search`
* See examples: :doc:`../resources/examples`