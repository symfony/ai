.. card:
    title: Build a RAG Pipeline
    description: Index documents into a vector store and query them with an AI agent.
    icon: database-search
    components: Store, Agent

Implementing Retrieval Augmented Generation (RAG)
=================================================

This guide walks you through implementing a complete RAG (Retrieval Augmented Generation) system
using Symfony AI. Instead of relying solely on the model's training data, a RAG system converts
documents into vector embeddings, stores them in a vector database, finds the documents most
similar to the user's query, and feeds that context to the language model to generate accurate,
context-aware responses. It is ideal for knowledge bases, product catalogs, customer support
systems, and domain-specific chatbots.

Prerequisites
-------------

* Symfony AI Platform component
* Symfony AI Agent component
* Symfony AI Store component
* An embeddings model (e.g., OpenAI's text-embedding-3-small)
* A language model (e.g., gpt-4o-mini)
* Optional: A vector store (or use in-memory for testing)

You can follow the complete example here: `in-memory.php <https://github.com/symfony/ai/blob/main/examples/rag/in-memory.php>`_

Step 1: Initialize the Vector Store
-----------------------------------

First, create a store to hold your vector embeddings::

    use Symfony\AI\Store\InMemory\Store;

    $store = new Store();

This in-memory store is ideal for getting started. See `Going to Production`_ for persistent stores.

Step 2: Prepare Your Documents
------------------------------

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
---------------------------------------------

Use a vectorizer to convert documents into embeddings and store them::

    use Symfony\AI\Store\Document\Vectorizer;
    use Symfony\AI\Store\Indexer\DocumentIndexer;
    use Symfony\AI\Store\Indexer\DocumentProcessor;

    $platform = Factory::createPlatform(env('OPENAI_API_KEY'));
    $vectorizer = new Vectorizer($platform, 'text-embedding-3-small');
    $indexer = new DocumentIndexer(new DocumentProcessor($vectorizer, $store));
    $indexer->index($documents);

The :class:`Symfony\\AI\\Store\\Indexer\\DocumentIndexer` accepts
:class:`Symfony\\AI\\Store\\Document\\EmbeddableDocumentInterface` instances (or iterables of them) directly.
It handles:

* Transforming and/or filtering documents (optional)
* Generating vector embeddings
* Storing vectors in the vector store

Alternatively, you can use the :class:`Symfony\\AI\\Store\\Indexer\\SourceIndexer` when you want to load
documents from a source (file path, URL, etc.) via a :class:`Symfony\\AI\\Store\\Document\\LoaderInterface`::

    use Symfony\AI\Store\Document\Loader\TextFileLoader;
    use Symfony\AI\Store\Indexer\SourceIndexer;

    $loader = new TextFileLoader();
    $indexer = new SourceIndexer($loader, new DocumentProcessor($vectorizer, $store));
    $indexer->index('/path/to/document.txt');

Step 4: Configure Similarity Search Tool
----------------------------------------

Create a tool that performs semantic search on your vector store::

    use Symfony\AI\Agent\Bridge\SimilaritySearch\SimilaritySearch;
    use Symfony\AI\Agent\Toolbox\AgentProcessor;
    use Symfony\AI\Agent\Toolbox\Toolbox;
    use Symfony\AI\Store\Retriever;

    $retriever = new Retriever($store, $vectorizer);
    $similaritySearch = new SimilaritySearch($retriever);
    $toolbox = new Toolbox([$similaritySearch]);
    $processor = new AgentProcessor($toolbox);

The :class:`Symfony\\AI\\Agent\\Bridge\\SimilaritySearch\\SimilaritySearch` tool:

* Uses the retriever to find similar documents in the store
* Returns the most relevant documents

You can customize the result header by passing a prompt template::

    $similaritySearch = new SimilaritySearch($retriever, 'Here are the relevant results:');

Step 5: Create RAG-Enabled Agent
--------------------------------

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
--------------------------

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

Going to Production
-------------------

The in-memory store above is perfect for testing, but production systems need a persistent
vector store such as ChromaDB, Pinecone, MongoDB Atlas, or Weaviate. Swap the store for a
bridge implementation and keep the rest of the pipeline unchanged. See :doc:`../components/store`
for the available stores, similarity strategies, metadata filtering, and document chunking via
:class:`Symfony\\AI\\Store\\Document\\Transformer\\TextSplitTransformer`.

If you use the AI Bundle, the same pipeline is wired through YAML (platform, vectorizer, store,
indexer, and agent) and populated with the ``ai:store:setup`` and ``ai:store:index`` commands.
See :doc:`../bundles/ai-bundle` for the full configuration reference.

Learn More
----------

* `RAG examples (ChromaDB, MongoDB, Pinecone, Meilisearch) <https://github.com/symfony/ai/tree/main/examples/rag>`_
* :doc:`../components/store` - Store component documentation
* :doc:`../components/agent` - Agent component documentation
* :doc:`../bundles/ai-bundle` - AI Bundle configuration
* :doc:`chatbot-with-memory` - Memory management guide
