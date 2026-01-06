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
