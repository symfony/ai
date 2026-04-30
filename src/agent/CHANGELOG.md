CHANGELOG
=========

0.9
---

 * Add `Symfony\AI\Agent\InterruptibleTrait` centralising the handling of `options['interruption_signal']` across agents. The base `Agent` now checks the signal before input processors, between input processors and platform invoke, and between platform invoke and output processors. `MultiAgent` applies the same checks around orchestration and delegation phases.
 * Add cooperative interruption between phases in `SpeechAgent`: an `InterruptionSignal` passed via `options['interruption_signal']` is checked before STT, between STT and LLM, and between LLM and TTS, throwing an `InterruptedException` when fired. `SpeechSession` creates and manages the signal automatically so that a new `call()` flips the previous signal for phase-boundary abortion in event-loop contexts (Fibers, ReactPHP, amphp).
 * Add `SpeechSession` decorator in `Symfony\AI\Agent\Speech\SpeechSession` that retains the last cancellable result and automatically cancels it when a new `call()` arrives, for long-running contexts (WebSocket, CLI daemons) where a fresh user input must supersede an in-flight pipeline.

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
