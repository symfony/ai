UPGRADE FROM 0.6 to 0.7
=======================

Agent
-----

 * The `Symfony\AI\Agent\Toolbox\ToolFactory\AbstractToolFactory` class has been removed. If you extended it to create
   a custom tool factory, implement `ToolFactoryInterface` yourself and use `Symfony\AI\Platform\Contract\JsonSchema\Factory`
   directly if needed.
 * The `ToolFactoryInterface::getTool()` method signature has changed to accept `object|string` instead of `string`:

   ```diff
   -public function getTool(string $reference): iterable;
   +public function getTool(object|string $reference): iterable;
   ```

 * The `SimilaritySearch` tool now requires a `RetrieverInterface` instead of `VectorizerInterface` and `StoreInterface`:

   ```diff
   -use Symfony\AI\Store\Document\VectorizerInterface;
   -use Symfony\AI\Store\StoreInterface;
   +use Symfony\AI\Store\Retriever;

   -$similaritySearch = new SimilaritySearch($vectorizer, $store);
   +$retriever = new Retriever($store, $vectorizer);
   +$similaritySearch = new SimilaritySearch($retriever);
   ```

 * The `SimilaritySearch` tool now accepts an optional `$promptTemplate` parameter to customize the result header (default: `'Found documents with the following information:'`)
 * `Toolbox\StreamListener::onChunk()` has been renamed to `onDelta()`.
   The listener now checks `$delta instanceof TextDelta` instead of
   `is_string($chunk)` to accumulate the assistant text buffer.
 * The `StreamListener` now reacts to `ToolCallComplete` instead of
   `ToolCallResult`. If you have a custom `StreamListener` or check for
   `ToolCallResult` in stream consumers, update to `ToolCallComplete`.

AI Bundle
---------

 * The traceable profiler decorators have been moved to their respective components. Update your imports if you
   reference them directly:

   ```diff
   -use Symfony\AI\AiBundle\Profiler\TraceablePlatform;
   +use Symfony\AI\Platform\TraceablePlatform;

   -use Symfony\AI\AiBundle\Profiler\TraceableAgent;
   +use Symfony\AI\Agent\TraceableAgent;

   -use Symfony\AI\AiBundle\Profiler\TraceableToolbox;
   +use Symfony\AI\Agent\Toolbox\TraceableToolbox;

   -use Symfony\AI\AiBundle\Profiler\TraceableStore;
   +use Symfony\AI\Store\TraceableStore;

   -use Symfony\AI\AiBundle\Profiler\TraceableChat;
   +use Symfony\AI\Chat\TraceableChat;

   -use Symfony\AI\AiBundle\Profiler\TraceableMessageStore;
   +use Symfony\AI\Chat\TraceableMessageStore;
   ```

 * The `api_catalog` option for `Ollama` has been removed as the catalog is now automatically fetched from the Ollama server
 * The `api_key` option for `Ollama` is now `null` by default to allow the usage of a `ScopingHttpClient`
 * The `endpoint` option for `Ollama` is now `null` by default to allow the usage of a `ScopingHttpClient`
 * The `strategy` option for `Cache` store is now `cosine` by default
 * The `DistanceCalculator` is no longer a service when using `Cache` store

Chat
----

 * The `ChatInterface` now has a `stream()` method. If you implement this interface,
   you need to add this method to your implementation.

Mate
----

 * Run `vendor/bin/mate discover` to update the generated `AGENT_INSTRUCTIONS.md` file with the latest
   tool descriptions and agent instructions.

Platform
--------

 * `ModelCatalog` in `Ollama` has been replaced by `OllamaApiCatalog`
 * `OllamaApiCatalog` in `Ollama` has been renamed to `ModelCatalog`
 * `Ollama` model is now `final`
 * `ModelCatalog` in `ElevenLabs` has been replaced by `ElevenLabsApiCatalog`
 * `ElevenLabsApiCatalog` in `ElevenLabs` has been renamed to `ModelCatalog`
 * `ChunkEvent` has been replaced by `DeltaEvent`. The `onChunk(ChunkEvent)` method
   on `ListenerInterface` is now `onDelta(DeltaEvent)`. Update all implementations:
   - `onChunk(ChunkEvent $event)` → `onDelta(DeltaEvent $event)`
   - `$event->getChunk()` → `$event->getDelta()`
   - `$event->setChunk(...)` → `$event->setDelta(...)`
   - `$event->skipChunk()` → `$event->skipDelta()`
   - `$event->isChunkSkipped()` → `$event->isDeltaSkipped()`
 * Stream generators in bridge `ResultConverter` classes now yield typed
   `DeltaInterface` objects instead of raw strings. Consumers iterating
   `StreamResult::getContent()` that expect `string` chunks must check for
   `TextDelta` instead:
   - `is_string($chunk)` → `$chunk instanceof TextDelta`, then use `$chunk->getText()`
 * New streaming delta types in `Symfony\AI\Platform\Result\Stream\Delta\`:
   `TextDelta`, `ThinkingDelta`, `ThinkingSignature`, `ThinkingStart`,
   `ToolCallStart`, `ToolInputDelta`, `BinaryDelta`, `ChoiceDelta`,
   `ToolCallComplete`, `ThinkingComplete`. These are yielded by bridge converters during streaming.
 * `Symfony\AI\Platform\Bridge\Ollama\OllamaMessageChunk` has been removed.
   Ollama streams now yield semantic deltas like the other bridges:
   - text content → `TextDelta`
   - thinking content → `ThinkingDelta`
   - completed tool calls → `ToolCallComplete`
   - final usage → `TokenUsage`

   ```diff
   -if ($chunk instanceof OllamaMessageChunk) {
   -    echo $chunk->getContent();
   -}
   +if ($chunk instanceof TextDelta) {
   +    echo $chunk->getText();
   +}
   ```
 * `Symfony\AI\Platform\Bridge\Perplexity\PerplexitySearchResults`,
   `Symfony\AI\Platform\Bridge\Perplexity\PerplexityCitations`, and
   `Symfony\AI\Platform\Bridge\Perplexity\StreamListener` have been
   removed. Perplexity now emits generic `MetadataDelta` instances internally,
   which are promoted to result metadata during stream consumption.

   If you relied on those deltas directly, read metadata instead:

   ```diff
   -if ($delta instanceof PerplexitySearchResults) {
   -    $results = $delta->getSearchResults();
   -}
   -if ($delta instanceof PerplexityCitations) {
   -    $citations = $delta->getCitations();
   -}
   +$results = $result->getMetadata()->get('search_results');
   +$citations = $result->getMetadata()->get('citations');
   ```
 * `BinaryResult`, `ChoiceResult`, `ToolCallResult`, and `ThinkingContent` no
   longer implement `DeltaInterface`. Stream generators now yield proper delta
   types instead of result objects:
   - `ToolCallResult` → `ToolCallComplete`: use `$delta instanceof ToolCallComplete`
     and `$delta->getToolCalls()`.
   - `ThinkingContent` → `ThinkingComplete`: use `$delta instanceof ThinkingComplete`
     and `$delta->getThinking()` / `$delta->getSignature()`.
   - `BinaryResult` → `BinaryDelta`: use `$delta instanceof BinaryDelta`
     and `$delta->getData()` / `$delta->getMimeType()`.
   - `ChoiceResult` → `ChoiceDelta`: use `$delta instanceof ChoiceDelta`
     and `$delta->getDeltas()`.
 * `ThinkingContent` has been removed in favor of `ThinkingComplete`.
 * The `Usage` delta class has been removed. Bridges now yield `TokenUsage`
   objects directly in streams instead.

Store
-----

 * The `Retriever` constructor has a new `?EventDispatcherInterface $eventDispatcher` parameter
   inserted as 3rd argument before `LoggerInterface $logger`. If you pass `$logger` positionally,
   update to use a named argument:

   ```diff
   -$retriever = new Retriever($store, $vectorizer, $logger);
   +$retriever = new Retriever($store, $vectorizer, logger: $logger);
   ```
 * Add support for `ScopingHttpClient` in `AzureSearchStore`
 * The `endpointUrl` parameter for `AzureSearchStore` has been removed
 * The `apiKey` parameter for `AzureSearchStore` has been removed
 * The `apiVersion` parameter for `AzureSearchStore` has been removed
 * A `StoreFactory` has been introduced for `AzureSearchStore`
 * The `endpointUrl` parameter for Weaviate `Store` has been removed
 * The `apiKey` parameter for Weaviate `Store` has been removed
 * A `StoreFactory` has been introduced for `Cache`

UPGRADE FROM 0.5 to 0.6
=======================

AI Bundle
---------

 * The `api_key` option for `ElevenLabs` is not required anymore if a `ScopedHttpClient` is used in `http_client` option

Platform
--------

 * The `PlatformFactory` is now in charge of creating `ElevenLabsApiCatalog` if `apiCatalog` is provided as `true`
 * `Symfony\AI\Platform\TokenUsage\TokenUsageInterface` has two new methods:
   `getCacheCreationTokens()` and `getCacheReadTokens()`

UPGRADE FROM 0.4 to 0.5
=======================

Platform
--------

 * The `hostUrl` parameter for `ElevenLabsClient` has been removed
 * The `host` parameter for `ElevenLabsApiCatalog` has been removed
 * The `hostUrl` parameter for `PlatformFactory::create()` in `ElevenLabs` has been renamed to `endpoint`

UPGRADE FROM 0.3 to 0.4
=======================

Agent
-----

 * The `keepToolMessages` parameter of `AgentProcessor` has been removed and replaced with `excludeToolMessages`.
   Tool messages (`AssistantMessage` with tool call invocations and `ToolCallMessage` with tool call results) are now
   **preserved** in the `MessageBag` by default.

   If you were opting in to keep tool messages, just remove the parameter:

   ```diff
   -$processor = new AgentProcessor($toolbox, keepToolMessages: true);
   +$processor = new AgentProcessor($toolbox);
   ```

   If you were explicitly discarding tool messages, use the new parameter:

   ```diff
   -$processor = new AgentProcessor($toolbox, keepToolMessages: false);
   +$processor = new AgentProcessor($toolbox, excludeToolMessages: true);
   ```

   If you were relying on the previous default (tool messages discarded), opt in explicitly:

   ```diff
   -$processor = new AgentProcessor($toolbox);
   +$processor = new AgentProcessor($toolbox, excludeToolMessages: true);
   ```

 * The `Symfony\AI\Agent\Toolbox\Tool\Agent` class has been renamed to `Symfony\AI\Agent\Toolbox\Tool\Subagent`:

   ```diff
   -use Symfony\AI\Agent\Toolbox\Tool\Agent;
   +use Symfony\AI\Agent\Toolbox\Tool\Subagent;

   -$agentTool = new Agent($agent);
   +$subagent = new Subagent($agent);
   ```

AI Bundle
---------

 * The service ID prefix for agent tools wrapping sub-agents has changed from `ai.toolbox.{agent}.agent_wrapper.`
   to `ai.toolbox.{agent}.subagent.`:

   ```diff
   -$container->get('ai.toolbox.my_agent.agent_wrapper.research_agent');
   +$container->get('ai.toolbox.my_agent.subagent.research_agent');
   ```

 * An indexer configured with a `source`, now wraps the indexer with a `Symfony\AI\Store\Indexer\ConfiguredSourceIndexer` decorator. This is
   transparent - the configured source is still used by default, but can be overridden by passing a source to `index()`.

 * The `host_url` parameter for `Ollama` platform has been renamed `endpoint`.

Platform
-------

 * The `hostUrl` parameter for `OllamaClient` has been removed
 * The `host` parameter for `OllamaApiCatalog` has been removed
 * The `hostUrl` parameter for `PlatformFactory::create()` in `Ollama` has been renamed to `endpoint`

Store
-----

 * The `Symfony\AI\Store\Indexer` class has been replaced with two specialized implementations:
   - `Symfony\AI\Store\Indexer\SourceIndexer`: For indexing from sources (file paths, URLs, etc.) using a `LoaderInterface`
   - `Symfony\AI\Store\Indexer\DocumentIndexer`: For indexing documents directly without a loader

   ```diff
   -use Symfony\AI\Store\Indexer;
   +use Symfony\AI\Store\Indexer\SourceIndexer;

   -$indexer = new Indexer($loader, $vectorizer, $store, '/path/to/source');
   -$indexer->index();
   +$indexer = new SourceIndexer($loader, $vectorizer, $store);
   +$indexer->index('/path/to/file');
   ```

   For indexing documents directly:

   ```php
   use Symfony\AI\Store\Document\TextDocument;
   use Symfony\AI\Store\Indexer\DocumentIndexer;

   $indexer = new DocumentIndexer($processor);
   $indexer->index(new TextDocument($id, 'content'));
   $indexer->index([$document1, $document2]);
   ```

 * The `Symfony\AI\Store\ConfiguredIndexer` class has been renamed to `Symfony\AI\Store\Indexer\ConfiguredSourceIndexer`:

   ```diff
   -use Symfony\AI\Store\ConfiguredIndexer;
   +use Symfony\AI\Store\Indexer\ConfiguredSourceIndexer;

   -$indexer = new ConfiguredIndexer($innerIndexer, 'default-source');
   +$indexer = new ConfiguredSourceIndexer($sourceIndexer, 'default-source');
   ```

 * The `Symfony\AI\Store\IndexerInterface::index()` method signature has changed - the input parameter is no longer nullable:

   ```diff
   -public function index(string|iterable|null $source = null, array $options = []): void;
   +public function index(string|iterable|object $input, array $options = []): void;
   ```

 * The `Symfony\AI\Store\IndexerInterface::withSource()` method has been removed. Use the `$source` parameter of `index()` instead:

   ```diff
   -$indexer->withSource('/new/source')->index();
   +$indexer->index('/new/source');
   ```

 * The `Symfony\AI\Store\Document\TextDocument` and `Symfony\AI\Store\Document\VectorDocument` classes now only accept
   `string` or `int` types for the `$id` property. The `Uuid` type has been removed and auto-casting to `uuid` in store
   bridges has been removed as well:

   ```diff
    use Symfony\AI\Store\Document\TextDocument;
    use Symfony\AI\Store\Document\VectorDocument;
    use Symfony\Component\Uid\Uuid;

    -$textDoc = new TextDocument(Uuid::v4(), 'content');
    +$textDoc = new TextDocument(Uuid::v4()->toString(), 'content');

    -$vectorDoc = new VectorDocument(Uuid::v4(), [0.1, ...]);
    +$vectorDoc = new VectorDocument(Uuid::v4()->toString(), [0.1, ...]);
    ```

 * The `Symfony\AI\Store\Document\EmbeddableDocumentInterface::getId()` can only return `string` or `int` types now.

 * Properties of `Symfony\AI\Store\Document\VectorDocument` and `Symfony\AI\Store\Document\Loader\Rss\RssItem` have been changed to private, use getters instead of public properties:

   ```diff
   -$document->id;
   -$document->metadata;
   -$document->score;
   -$document->vector;
   +$document->getId();
   +$document->getMetadata();
   +$document->getScore();
   +$document->getVector();
   ```

 * The `StoreInterface::query()` method signature has changed to accept a `QueryInterface` instead of a `Vector`:

   ```diff
   -use Symfony\AI\Platform\Vector\Vector;
   +use Symfony\AI\Store\Query\VectorQuery;

   -$results = $store->query($vector, ['limit' => 10]);
   +$results = $store->query(new VectorQuery($vector), ['limit' => 10]);
   ```

   The Store component now supports multiple query types through a query abstraction system. Available query types:
   - `VectorQuery`: For vector similarity search (replaces the old `Vector` parameter)
   - `TextQuery`: For full-text search (when supported by the store)
   - `HybridQuery`: For combined vector + text search (when supported by the store)

   Check if a store supports a specific query type using the new `supports()` method:

   ```php
   if ($store->supports(VectorQuery::class)) {
       $results = $store->query(new VectorQuery($vector));
   }
   ```

 * The `Symfony\AI\Store\Retriever` constructor signature has changed - the first two arguments have been swapped:

   ```diff
   -use Symfony\AI\Store\Retriever;
   -
   -$retriever = new Retriever($vectorizer, $store, $logger);
   +$retriever = new Retriever($store, $vectorizer, $logger);
   ```

   This change aligns the constructor with the primary dependency (the store) being first, followed by the optional vectorizer.

UPGRADE FROM 0.2 to 0.3
=======================

Agent
-----

  * The `Symfony\AI\Agent\Toolbox\StreamResult` class has been removed in favor of a `StreamListener`. Checks should now target
    `Symfony\AI\Platform\Result\StreamResult` instead.
  * The `Symfony\AI\Agent\Toolbox\Source\SourceMap` class has been renamed to `SourceCollection`. Its methods have also been renamed:
    * `getSources()` is now `all()`
    * `addSource()` is now `add()`
  * The third argument of the `Symfony\AI\Agent\Toolbox\ToolResult::__construct()` method now expects a `SourceCollection` instead of an `array<int, Source>`

Platform
--------

 * The `TokenUsageAggregation::__construct()` method signature has changed from variadic to accept an array of `TokenUsageInterface`

   ```diff
   -$aggregation = new TokenUsageAggregation($usage1, $usage2);
   +$aggregation = new TokenUsageAggregation([$usage1, $usage2]);
   ```

 * The `Symfony\AI\Platform\CachedPlatform` has been renamed `Symfony\AI\Platform\Bridge\Cache\CachePlatform`
   * To use it, consider the following steps:
     * Run `composer require symfony/ai-cache-platform`
     * Change `Symfony\AI\Platform\CachedPlatform` namespace usages to `Symfony\AI\Platform\Bridge\Cache\CachePlatform`
     * The `ttl` option can be used in the configuration
 * Adopt usage of class `Symfony\AI\Platform\Serializer\StructuredOutputSerializer` to `Symfony\AI\Platform\StructuredOutput\Serializer`

UPGRADE FROM 0.1 to 0.2
=======================

AI Bundle
---------

 * Agents are now injected using their configuration name directly, instead of appending Agent or MultiAgent

   ```diff
   public function __construct(
   -   private AgentInterface $blogAgent,
   +   private AgentInterface $blog,
   -   private AgentInterface $supportMultiAgent,
   +   private AgentInterface $support,
   ) {}
   ```

Agent
-----

 * Constructor of `MemoryInputProcessor` now accepts an iterable of inputs instead of variadic arguments.

   ```php
   use Symfony\AI\Agent\Memory\MemoryInputProcessor;

   // Before
   $processor = new MemoryInputProcessor($input1, $input2);

   // After
   $processor = new MemoryInputProcessor([$input1, $input2]);
   ```

Platform
--------

 * The `ChoiceResult::__construct()` method signature has changed from variadic to accept an array of `ResultInterface`

   ```php
   use Symfony\AI\Platform\Result\ChoiceResult;

   // Before
   $choiceResult = new ChoiceResult($result1, $result2);

   // After
   $choiceResult = new ChoiceResult([$result1, $result2]);
   ```

Store
-----

* The `StoreInterface::remove()` method was added to the interface

  ```php
  public function remove(string|array $ids, array $options = []): void;

  // Usage
  $store->remove('vector-id-1');
  $store->remove(['vid-1', 'vid-2']);
  ```

 * The `StoreInterface::add()` method signature has changed from variadic to accept a single document or an array

   *Before:*
   ```php
   public function add(VectorDocument ...$documents): void;

   // Usage
   $store->add($document1, $document2);
   $store->add(...$documents);
   ```

   *After:*
   ```php
   public function add(VectorDocument|array $documents): void;

   // Usage
   $store->add($document);
   $store->add([$document1, $document2]);
   $store->add($documents);
   ```
