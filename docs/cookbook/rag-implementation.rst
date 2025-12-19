Implementing Retrieval Augmented Generation (RAG)
=================================================

This guide walks you through implementing a complete RAG (Retrieval Augmented Generation)
system using Symfony AI. RAG allows your agent to retrieve relevant information from a
knowledge base and use it to generate accurate, context-aware responses.

What is RAG?
------------

Retrieval Augmented Generation combines the power of vector search with language models
to provide agents with access to external knowledge. Instead of relying solely on the
model's training data, RAG systems:

1. Convert documents into vector embeddings
2. Store embeddings in a vector database
3. Find similar documents based on user queries
4. Provide retrieved context to the language model
5. Generate responses based on the retrieved information

This approach is ideal for:

* Knowledge bases and documentation
* Product catalogs
* Customer support systems
* Research assistants
* Domain-specific chatbots

Prerequisites
-------------

* Symfony AI Platform component
* Symfony AI Agent component
* Symfony AI Store component
* An embeddings model (e.g., OpenAI's text-embedding-3-small)
* A language model (e.g., gpt-4o-mini)
* Optional: A vector store (or use in-memory for testing)

Complete Implementation
-----------------------

See the complete example: `in-memory.php <https://github.com/symfony/ai/blob/main/examples/rag/in-memory.php>`_

Step-by-Step Breakdown
----------------------

Step 1: Initialize the Vector Store
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

First, create a store to hold your vector embeddings::

    use Symfony\AI\Store\InMemory\Store;

    $store = new Store();

For production use, consider using persistent stores like ChromaDB, Pinecone, or MongoDB Atlas.

Step 2: Prepare Your Documents
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Create text documents with relevant content and metadata::

    use Symfony\AI\Store\Document\Metadata;
    use Symfony\AI\Store\Document\TextDocument;
    use Symfony\Component\Uid\Uuid;

    $documents = [];
    foreach ($movies as $movie) {
        $documents[] = new TextDocument(
            id: Uuid::v4(),
            content: 'Title: '.$movie['title'].PHP_EOL.
                    'Director: '.$movie['director'].PHP_EOL.
                    'Description: '.$movie['description'],
            metadata: new Metadata($movie),
        );
    }

Each document should contain:

* **ID**: Unique identifier (UUID v4 recommended)
* **Content**: The text to be embedded and searched
* **Metadata**: Additional information preserved with the document

Step 3: Create Embeddings and Index Documents
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Use a vectorizer to convert documents into embeddings and store them::

    use Symfony\AI\Store\Document\Loader\InMemoryLoader;
    use Symfony\AI\Store\Document\Vectorizer;
    use Symfony\AI\Store\Indexer;

    $platform = PlatformFactory::create(env('OPENAI_API_KEY'));
    $vectorizer = new Vectorizer($platform, 'text-embedding-3-small');
    $indexer = new Indexer(
        new InMemoryLoader($documents),
        $vectorizer,
        $store
    );
    $indexer->index($documents);

The indexer handles:

* Loading documents from the source
* Generating vector embeddings
* Storing vectors in the vector store

Step 4: Configure Similarity Search Tool
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Create a tool that performs semantic search on your vector store::

    use Symfony\AI\Agent\Bridge\SimilaritySearch\SimilaritySearch;
    use Symfony\AI\Agent\Toolbox\AgentProcessor;
    use Symfony\AI\Agent\Toolbox\Toolbox;

    $similaritySearch = new SimilaritySearch($vectorizer, $store);
    $toolbox = new Toolbox([$similaritySearch]);
    $processor = new AgentProcessor($toolbox);

The :class:`Symfony\\AI\\Agent\\Bridge\\SimilaritySearch\\SimilaritySearch` tool:

* Converts the user's query into a vector
* Searches for similar vectors in the store
* Returns the most relevant documents

Step 5: Create RAG-Enabled Agent
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Configure the agent with the similarity search processor::

    use Symfony\AI\Agent\Agent;

    $agent = new Agent(
        $platform,
        'gpt-4o-mini',
        [$processor],  // Input processors
        [$processor]   // Output processors
    );

The agent will automatically use the similarity search tool when needed.

Step 6: Query with Context
~~~~~~~~~~~~~~~~~~~~~~~~~~

Create messages that instruct the agent to use the similarity search::

    use Symfony\AI\Platform\Message\Message;
    use Symfony\AI\Platform\Message\MessageBag;

    $messages = new MessageBag(
        Message::forSystem('Please answer all user questions only using SimilaritySearch function.'),
        Message::ofUser('Which movie fits the theme of the mafia?')
    );
    $result = $agent->call($messages);

The agent will:

1. Analyze the user's question
2. Call the similarity search tool
3. Retrieve relevant documents
4. Generate a response based on the retrieved context

Production-Ready RAG Systems
----------------------------

Vector Store Selection
~~~~~~~~~~~~~~~~~~~~~~

For production environments, use persistent vector stores like ChromaDB::

    use Symfony\AI\Store\Bridge\ChromaDb\Store;

    $store = new Store(
        $httpClient,
        'http://localhost:8000',
        'my_collection'
    );

ChromaDB is a great choice for production RAG systems as it provides:

* Local or self-hosted deployment options
* Efficient vector similarity search
* Built-in persistence
* Easy integration with Symfony AI

See :doc:`../components/store` for all supported vector stores including Pinecone, MongoDB Atlas, Weaviate, and more.

Document Loading Strategies
~~~~~~~~~~~~~~~~~~~~~~~~~~~

**File-based loading** for static content::

    use Symfony\AI\Store\Document\Loader\TextFileLoader;

    $loader = new TextFileLoader('/path/to/documents');

**Database loading** for dynamic content::

    use Symfony\AI\Store\Document\Loader\InMemoryLoader;

    // Fetch from database
    $articles = $articleRepository->findAll();
    $documents = array_map(
        fn($article) => new TextDocument(
            id: Uuid::fromString($article->getId()),
            content: $article->getTitle().PHP_EOL.$article->getContent(),
            metadata: new Metadata(['author' => $article->getAuthor()])
        ),
        $articles
    );

    $loader = new InMemoryLoader($documents);

Advanced Configurations
-----------------------

Chunking Large Documents
~~~~~~~~~~~~~~~~~~~~~~~~

For large documents, split them into smaller chunks for better retrieval using the
:class:`Symfony\\AI\\Store\\Document\\Transformer\\TextSplitTransformer`::

    use Symfony\AI\Store\Document\Transformer\TextSplitTransformer;

    $transformer = new TextSplitTransformer(
        chunkSize: 1000,
        overlap: 200
    );

    $chunkedDocuments = $transformer->transform($documents);

The transformer automatically:

* Splits documents into chunks of the specified size
* Adds overlap between chunks to maintain context
* Preserves original document metadata
* Tracks parent document IDs for reference

Custom Similarity Metrics
~~~~~~~~~~~~~~~~~~~~~~~~~

Some vector stores support different similarity metrics:

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        store:
            memory:
                default:
                    strategy: 'cosine'  # or 'euclidean', 'manhattan', 'chebyshev'

Metadata Filtering
~~~~~~~~~~~~~~~~~~

ChromaDB supports filtering search results based on metadata using the ``where`` option::

    $result = $store->query($vector, [
        'where' => [
            'category' => 'technical',
            'status' => 'published',
        ],
    ]);

You can also filter based on document content using ``whereDocument``::

    $result = $store->query($vector, [
        'where' => ['category' => 'technical'],
        'whereDocument' => ['$contains' => 'machine learning'],
    ]);

Bundle Configuration
--------------------

When using the AI Bundle, configure RAG with YAML:

.. code-block:: yaml

    # config/packages/ai.yaml
    ai:
        platform:
            openai:
                api_key: '%env(OPENAI_API_KEY)%'

        vectorizer:
            default:
                platform: 'ai.platform.openai'
                model: 'text-embedding-3-small'

        store:
            chromadb:
                knowledge_base:
                    collection: 'docs'

        indexer:
            docs:
                loader: 'App\Document\Loader\DocLoader'
                vectorizer: 'ai.vectorizer.default'
                store: 'ai.store.chromadb.knowledge_base'

        agent:
            rag_assistant:
                model: 'gpt-4o-mini'
                prompt:
                    text: 'Answer questions using only the SimilaritySearch tool. If you cannot find relevant information, say so.'
                tools:
                    - 'Symfony\AI\Agent\Bridge\SimilaritySearch\SimilaritySearch'

Then use the indexer command to populate your store:

.. code-block:: terminal

    $ php bin/console ai:store:setup chromadb.knowledge_base
    $ php bin/console ai:store:index docs

Performance Optimization
------------------------

Batch Indexing
~~~~~~~~~~~~~~

Index documents in batches for better performance::

    $batchSize = 100;
    foreach (array_chunk($documents, $batchSize) as $batch) {
        $indexer->index($batch);
    }

Caching Embeddings
~~~~~~~~~~~~~~~~~~

Cache embeddings to avoid recomputing::

    use Symfony\Contracts\Cache\CacheInterface;

    class CachedVectorizer
    {
        public function __construct(
            private Vectorizer $vectorizer,
            private CacheInterface $cache,
        ) {
        }

        public function vectorize(string $text): Vector
        {
            $key = 'embedding_'.md5($text);

            return $this->cache->get($key, function() use ($text) {
                return $this->vectorizer->vectorize($text);
            });
        }
    }

Best Practices
--------------

1. **Document Quality**: Ensure documents are well-structured and contain relevant information
2. **Chunk Size**: Experiment with different chunk sizes (500-1500 tokens typical)
3. **Metadata**: Include useful metadata for filtering and context
4. **System Prompt**: Explicitly instruct the agent to use the similarity search tool
5. **Limit Results**: Configure appropriate limits to balance relevance and context size
6. **Update Strategy**: Plan for incremental updates vs. full reindexing
7. **Monitor Performance**: Track query latency and relevance metrics
8. **Test Queries**: Validate that retrieval returns expected results

Common Pitfalls
---------------

* **Too Large Chunks**: Large chunks reduce retrieval precision
* **Too Small Chunks**: Small chunks lose context
* **Missing Instructions**: Agent needs explicit instructions to use similarity search
* **Poor Document Quality**: Garbage in, garbage out
* **Incorrect Embeddings Model**: Use the same model for indexing and querying
* **No Metadata**: Missing metadata limits filtering capabilities

Related Examples
----------------

* `RAG with ChromaDB <https://github.com/symfony/ai/blob/main/examples/rag/chromadb.php>`_
* `RAG with MongoDB <https://github.com/symfony/ai/blob/main/examples/rag/mongodb.php>`_
* `RAG with Pinecone <https://github.com/symfony/ai/blob/main/examples/rag/pinecone.php>`_
* `RAG with Meilisearch <https://github.com/symfony/ai/blob/main/examples/rag/meilisearch.php>`_

Related Documentation
---------------------

* :doc:`../components/store` - Store component documentation
* :doc:`../components/agent` - Agent component documentation
* :doc:`../bundles/ai-bundle` - AI Bundle configuration
* :doc:`chatbot-with-memory` - Memory management guide
