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
