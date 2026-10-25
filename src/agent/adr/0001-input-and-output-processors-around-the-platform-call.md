---
status: accepted
date: 2026-10-25
---

# 0001. Input and output processors around the platform call

## Context and Problem Statement

An agent does more than one call to `PlatformInterface::invoke()`. It adds a system
prompt, injects memory, switches the model per call or changes the answer. If each of
these features lived inside `Agent`, the class would grow with every feature and users
would have to fork it for custom behavior. The agent needs one stable place where such
features hook in before and after the model call.

The first `Chain` took a toolbox, a response format factory and a serializer as
constructor arguments and had special code for each. Processors replaced that.

## Decision

`Agent` (`src/Agent.php`) runs two ordered lists of processors around the model call:

- Every `InputProcessorInterface::processInput(Input $input)` runs before the platform
  call. `Input` (`src/Input.php`) holds the model name, the `MessageBag` and the
  options, and a processor can change all three.
- Every `OutputProcessorInterface::processOutput(Output $output)` runs after the result
  exists. `Output` (`src/Output.php`) shows the model, messages and options read-only
  and lets a processor replace the result with `setResult()`.
- A processor that implements `AgentAwareInterface` gets the calling agent through
  `setAgent()` before it runs.
- Processors are passed as `iterable`s to the constructor and checked at call time. A
  wrong type throws `Exception\InvalidArgumentException`.

Built-in features use this hook instead of special code in `Agent`:
`InputProcessor\SystemPromptInputProcessor`, `InputProcessor\ModelOverrideInputProcessor`
and `Memory\MemoryInputProcessor`. `MemoryInputProcessor` adds the `Memory\Memory` items
of every `Memory\MemoryProviderInterface` (for example `StaticMemoryProvider` or the
store-backed `EmbeddingProvider`) to the system message. The `use_memory: false` option
turns it off. With the `#[AsInputProcessor]` and `#[AsOutputProcessor]` attributes, a
framework can register processors per agent and with a priority.

## Consequences

- **Good:** a new feature is a small class with one method. It can be tested alone and
  does not touch `Agent`.
- **Good:** `Agent` stays `final`; customization happens through composition.
- **Trade-off:** processors share only the mutable `Input`/`Output` objects, so order
  matters. Two processors that edit the system message (system prompt and memory) must
  cooperate, as `MemoryInputProcessor` does with its `memory_original_system_prompt`
  metadata key.
- **Trade-off:** processor options travel in the same `options` array as platform
  options, so a processor must remove its own keys (as `use_memory` is removed) before
  they reach the platform.

## Alternatives Considered

- **Fixed constructor arguments on the agent** (toolbox, response format factory,
  serializer): the first design, replaced by processors in php-llm/llm-chain#47.
- **Memory as a tool**: rejected in #117, because the model would have to be told to
  always call that tool, even when it does not need to.
- **Subclassing `Agent`**: not chosen; inheritance does not combine several independent
  features.

## History

- 2024-09-26, php-llm/llm-chain#47 by Christopher Hertel: added input and output processors and moved the toolbox and structured output out of `Chain` into them.
- 2024-10-05, php-llm/llm-chain#118 by Christopher Hertel: added chain awareness (today `AgentAwareInterface`) so processors can call the agent again.
- 2025-01-04, php-llm/llm-chain#184 and php-llm/llm-chain#186 by Christopher Hertel: input processors may change the model and the `MessageBag`; added the system prompt processor.
- 2025-07-16, #117 by @DZunke (Denis Zunke): added memory providers as an input processor that writes into the system prompt.
- 2025-09-02, #352 by @VincentLanglet (Vincent Langlet): added `#[AsInputProcessor]` and `#[AsOutputProcessor]` with a priority to control order.
- 2026-08-07, #2373 by @chr-hertel (Christopher Hertel): moved tool calling out of the processors into the agent itself (see ADR 0003).

## References

- `src/Agent.php`: runs the input processors, the runner, then the output processors.
- `src/InputProcessorInterface.php`, `src/OutputProcessorInterface.php`: the contracts.
- `src/Input.php`, `src/Output.php`: the data containers.
- `src/AgentAwareInterface.php`: access to the calling agent.
- `src/Memory/MemoryInputProcessor.php`, `src/Memory/MemoryProviderInterface.php`: memory
  as an input processor.
- `src/Attribute/AsInputProcessor.php`, `src/Attribute/AsOutputProcessor.php`: registration.
