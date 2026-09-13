CHANGELOG
=========

0.14
----

 * Add `Execution::cancel()` to stop an active execution and cancel its active HTTP response
 * `MultiAgent` and `SpeechAgent` now forward the `Progress` updates of the executions they delegate to, and `MultiAgent` reports its routing as a `Progress` update of the `handoff` stage carrying the orchestrator's `MultiAgent\Handoff\Decision` as payload
 * [BC BREAK] `Bridge\SimilaritySearch\SimilaritySearch::getUsedDocuments()` returns `Store\Document\VectorDocumentInterface[]` instead of `Store\Document\VectorDocument[]`, following the retriever it reads from

0.13
----

 * `ValidateToolCallArgumentsListener` now also validates scalar and array tool parameters carrying a `#[Schema]` attribute (`pattern`, `minLength`/`maxLength`, `minimum`/`maximum`/`exclusiveMinimum`/`exclusiveMaximum`, `multipleOf`, `minItems`/`maxItems`, `uniqueItems`, `enum`, `const`), not only object parameters validated through Symfony Validator constraints
 * [BC BREAK] Remove `Toolbox\AgentProcessor`; tool calling is now driven by the `Agent` itself, configured with the `toolbox`, `toolExecutor`, `maxToolCalls`, `excludeToolMessages`, `includeSources` and `eventDispatcher` constructor arguments
 * Add `Toolbox\ToolExecutorInterface` and its default `SequentialToolExecutor` implementation, making the execution of the requested tool calls replaceable
 * `SystemPromptInputProcessor` now accepts and preserves a `Template`
 * [BC BREAK] Change `AgentInterface::call()` to return a lazy, iterable `Execution` that is also the `ResultInterface` it produces — read it eagerly (`getContent()`, `getResult()`), iterate it to observe every model request, tool call and streamed delta as a `Progress` update, or register `onProgress()`/`onResult()` callbacks
 * [BC BREAK] Remove `Toolbox\StreamListener`; streamed rounds are consumed by the agent itself and their deltas are reported as `Progress` updates of the `delta` stage, surfaced by the streamed execution's `getContent()`
 * Add the typed accessors `asText()`, `asObject()`, `asBinary()`, `asFile()`, `asDataUri()`, `asToolCalls()`, `asStream()`, `asTextStream()`, `asStreamedObject()` and `asPartialJsonStream()` to `Execution`, narrowing the produced result or its streamed deltas like `DeferredResult` does

0.12
----

 * [BC BREAK] Change `AgentInterface::call()` to accept `string|MessageBag|UserMessage` and rename its first parameter from `$messages` to `$input`
 * [BC BREAK] Change the `input` key of `TraceableAgent::getCalls()` entries, which was previously named `messages`, to carry the untouched input
 * Add `Agent::call()` support for plain strings and single `UserMessage` objects, e.g. `$agent->call('Hello world')`

0.11
----

 * Add `metadata` support for tools to allow attaching custom data
 * [BC BREAK] Rename `getMetadata()` to `getDefinition()` on the tool call events (`ToolCallRequested`, `ToolCallArgumentsResolved`, `ToolCallSucceeded`, `ToolCallFailed`), since it returns the tool definition and the "metadata" name is now used for the tool's classification

0.10
----

 * Add `SystemPromptInputProcessor::getSystemPrompt()` to read the configured system prompt without reflection
 * [BC BREAK] Change the default value of the `maxToolCalls` parameter of `AgentProcessor` from `null` (unbounded) to `50`. Pass `null` explicitly to restore the previous unbounded behaviour.

0.8
---

 * [BC BREAK] Reduce visibility of `SimilaritySearch::$usedDocuments` to `private`; use `getUsedDocuments()` instead
 * [BC BREAK] Change `public array $calls` to `private array $calls` in `TraceableAgent` and `TraceableToolbox` - use `getCalls()` instead
 * [BC BREAK] Change `StaticMemoryProvider` constructor from variadic `string ...$memory` to `array $memory`
 * [BC BREAK] Change `ToolCallsExecuted` constructor from variadic `ToolResult ...$toolResults` to `array $toolResults`

0.7
---

 * Add `TraceableAgent` and `TraceableToolbox` profiler decorators moved from AI Bundle
 * [BC BREAK] Change `SimilaritySearch` to use `RetrieverInterface` instead of `VectorizerInterface` and `StoreInterface`
 * Add customizable `$promptTemplate` parameter to `SimilaritySearch` constructor
 * [BC BREAK] Remove `AbstractToolFactory` in favor of standalone `ReflectionToolFactory` and `MemoryToolFactory`
 * [BC BREAK] Change `ToolFactoryInterface::getTool()` signature from `string $reference` to `object|string $reference`
 * Add `ToolCallRequested` event dispatched before tool execution
 * Update `StreamListener` to use `DeltaEvent` and `TextDelta` instead of `ChunkEvent` and raw strings
 * Update `StreamListener` to react to `ToolCallComplete` instead of `ToolCallResult`
 * Add `ValidateToolCallArgumentsListener` to validate tool call arguments with `symfony/validator` component
 * Add `SpeechAgent` decorator for speech-to-text and text-to-speech capabilities

0.4
---

 * [BC BREAK] Rename `Symfony\AI\Agent\Toolbox\Tool\Agent` to `Symfony\AI\Agent\Toolbox\Tool\Subagent`
 * [BC BREAK] Change AgentProcessor `keepToolMessages` to `excludeToolMessages` and default behaviour to preserve tool messages
 * Add `MetaDataAwareTrait` to `MockResponse`, the metadata will also be set on the returned `TextResult` when calling the `toResult` function
 * Add `HasSourcesTrait` to `Symfony\AI\Agent\Toolbox\Tool\Subagent`

0.3
---

 * [BC BREAK] Drop toolboxes `StreamResult` in favor of `StreamListener` on top of Platform's `StreamResult`
 * [BC BREAK] Rename `SourceMap` to `SourceCollection`, its methods from `getSources()` to `all()` and `addSource()` to `add()`
 * [BC BREAK] Third Argument of `ToolResult::__construct()` now expects `SourceCollection` instead of `array<int, Source>`
 * Add `maxToolCalls` parameter to `AgentProcessor` to limit tool calling iterations and prevent infinite loops
 * Add `Countable` and `IteratorAggregate` implementations to `SourceCollection`

0.2
---

 * [BC BREAK] Switch `MemoryInputProcessor` to use `iterable` instead of variadic arguments

0.1
---

 * Add the component
