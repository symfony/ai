---
status: accepted
date: 2026-10-25
---

# 0001. Input and output processors around the platform call

## Context and Problem Statement

An agent is more than one call to `PlatformInterface::invoke()`: it adds a system prompt,
injects memory, switches the model per call or post-processes the answer. Building each
of these concerns into `Agent` would make the class grow with every feature and would
force users to fork it for custom behavior. The agent needs a stable place where such
concerns hook in before and after the model is invoked.

## Decision

`Agent` (`src/Agent.php`) runs two ordered chains of processors around the model call:

- Every `InputProcessorInterface::processInput(Input $input)` runs before the platform is
  invoked. `Input` (`src/Input.php`) is a mutable container of the model name, the
  `MessageBag` and the options, so a processor can change any of the three.
- Every `OutputProcessorInterface::processOutput(Output $output)` runs after the result
  is produced. `Output` (`src/Output.php`) exposes the model, messages and options
  read-only and lets a processor replace the result with `setResult()`.
- A processor that implements `AgentAwareInterface` receives the calling agent through
  `setAgent()` before it runs.
- Processors are passed as `iterable`s to the constructor and checked at call time; a
  wrong type throws `Exception\InvalidArgumentException`.

The built-in features use this extension point instead of special code in `Agent`:
`InputProcessor\SystemPromptInputProcessor`, `InputProcessor\ModelOverrideInputProcessor`
and `Memory\MemoryInputProcessor`. Memory itself is a further extension point:
`MemoryInputProcessor` merges the `Memory\Memory` items of every
`Memory\MemoryProviderInterface` (for example `StaticMemoryProvider` or the Store-backed
`EmbeddingProvider`) into the system message, and is skipped with the `use_memory: false`
option. The `#[AsInputProcessor]` and `#[AsOutputProcessor]` attributes let a framework
integration register processors per agent and with a priority.

## Consequences

- **Good:** new cross-cutting behavior is a small class with one method, testable in
  isolation, without touching `Agent`.
- **Good:** `Agent` stays `final`; customization happens through composition.
- **Trade-off:** processors communicate only through the mutable `Input`/`Output`
  objects, so order matters and two processors editing the system message (system prompt
  and memory) must be written to cooperate, as `MemoryInputProcessor` does with its
  `memory_original_system_prompt` metadata key.
- **Trade-off:** processor options travel in the same `options` array as platform
  options, so a processor has to remove its own keys (as `use_memory` is removed) before
  they reach the platform.

## Alternatives Considered

- **Event dispatcher for before/after hooks**: not chosen because processors must be
  usable without `symfony/event-dispatcher` and need to mutate the request in a defined
  order; events are used where listeners are optional, such as the tool call events.
- **Subclassing `Agent`**: not chosen because inheritance does not compose several
  independent concerns and would make the constructor a public extension contract.

## References

- `src/Agent.php`: runs the input processors, the runner, then the output processors.
- `src/InputProcessorInterface.php`, `src/OutputProcessorInterface.php`: the contracts.
- `src/Input.php`, `src/Output.php`: the data containers.
- `src/AgentAwareInterface.php`: access to the calling agent.
- `src/Memory/MemoryInputProcessor.php`, `src/Memory/MemoryProviderInterface.php`: memory
  as an input processor.
- `src/Attribute/AsInputProcessor.php`, `src/Attribute/AsOutputProcessor.php`: registration.
