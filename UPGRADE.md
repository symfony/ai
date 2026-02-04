UPGRADE FROM 0.3 to 0.4
=======================

Agent
-----

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

 * An indexer configured with a `source`, now wraps the indexer with a `Symfony\AI\Store\ConfiguredSourceIndexer` decorator. This is
   transparent - the configured source is still used by default, but can be overridden by passing a source to `index()`.

Store
-----

 * The `Symfony\AI\Store\Indexer` class has been replaced with two specialized implementations:
   - `Symfony\AI\Store\SourceIndexer`: For indexing from sources (file paths, URLs, etc.) using a `LoaderInterface`
   - `Symfony\AI\Store\DocumentIndexer`: For indexing documents directly without a loader

   ```diff
   -use Symfony\AI\Store\Indexer;
   +use Symfony\AI\Store\SourceIndexer;

   -$indexer = new Indexer($loader, $vectorizer, $store, '/path/to/source');
   -$indexer->index();
   +$indexer = new SourceIndexer($loader, $vectorizer, $store);
   +$indexer->index('/path/to/file');
   ```

   For indexing documents directly:

   ```php
   use Symfony\AI\Store\Document\TextDocument;use Symfony\AI\Store\Indexer\DocumentIndexer;

   $indexer = new DocumentIndexer($processor);
   $indexer->index(new TextDocument($id, 'content'));
   $indexer->index([$document1, $document2]);
   ```

 * The `Symfony\AI\Store\ConfiguredIndexer` class has been renamed to `Symfony\AI\Store\ConfiguredSourceIndexer`:

   ```diff
   -use Symfony\AI\Store\ConfiguredIndexer;
   +use Symfony\AI\Store\ConfiguredSourceIndexer;

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
 * Adopt usage of class `Symfony\AI\Platform\Serializer\StructuredOuputSerializer` to `Symfony\AI\Platform\StructuredOutput\Serializer`

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
   use Symfony\AI\Agent\InputProcessor\MemoryInputProcessor;

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
