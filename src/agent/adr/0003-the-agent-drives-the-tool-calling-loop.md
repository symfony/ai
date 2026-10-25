---
status: accepted
date: 2026-10-25
---

# 0003. The agent drives the tool-calling loop through a replaceable executor

## Context and Problem Statement

Function calling is a loop: the model asks for tools, the tools run, their results go
back to the model as messages, and the model is called again until it answers. Until
0.13 this loop lived in `Toolbox\AgentProcessor`, an input and output processor that
called the platform again from inside the output phase. Streamed tool calls needed a
separate `StreamListener`. This made streaming, progress and cancellation inside the
loop hard, and it blocked the planned rework of the processor pipeline. Tool calling is
the core feature of an agent, so it should not come in through a processor.

## Decision

The loop is part of the agent. `Agent` takes `toolbox`, `toolExecutor`, `maxToolCalls`
(default `50`), `excludeToolMessages`, `includeSources` and `eventDispatcher` and passes
them to the internal `Execution\Runner`, which:

- offers the toolbox's tools to the model through the `tools` option, limited to the
  names given in a per-call `tools` option, and rejects calls to tools that were not
  allowed;
- calls the model, reads a streamed round itself (passing text pieces on as `Progress`),
  and stops when the result has no tool calls;
- hands the requested calls to `Toolbox\ToolExecutorInterface`. Its
  `execute(array $toolCalls)` is a generator that yields updates and returns one
  `ToolResult` per call, in order. The default is `SequentialToolExecutor`;
  `FiberToolExecutor` runs calls concurrently;
- adds the assistant message and one tool call message per result, built by
  `ToolResultConverter`, then dispatches `Toolbox\Event\ToolCallsExecuted`, whose
  listener may end the loop with a final result;
- throws `Exception\MaxIterationsExceededException` after `maxToolCalls` rounds, and
  merges the metadata of all rounds into the final result.

## Consequences

- **Good:** streamed and non-streamed tool rounds use the same loop, and every model
  request and tool call is visible as a `Progress` update.
- **Good:** how calls run (one by one, concurrently, remotely) can be replaced without
  touching the loop. Executors do not build messages, so each strategy does not have to
  repeat the message format.
- **Good:** the loop is bounded out of the box; `maxToolCalls: null` removes the limit.
- **Trade-off:** `Agent` has a long constructor that mixes the pipeline with tool
  settings.
- **Trade-off:** without `excludeToolMessages`, the caller's `MessageBag` grows with the
  tool messages in between, and a chat stores them.

## Alternatives Considered

- **The loop inside `Chain::call()`**: the first design, moved into a toolbox processor
  in php-llm/llm-chain#47.
- **The loop in `Toolbox\AgentProcessor`**: removed in #2373, because a processor cannot
  yield progress or streamed text from inside the loop.
- **No limit by default**: `maxToolCalls` was added as an option in #1429 and made
  bounded by default in #2179.

## History

- 2024-09-26, php-llm/llm-chain#47 by Christopher Hertel: moved the tool loop from `Chain` into a toolbox processor.
- 2026-01-23, #1429 by @lochmueller (Tim Lochmüller): added `maxToolCalls` to limit the loop.
- 2026-06-16, #2179 by @chr-hertel (Christopher Hertel): the loop is bounded by default instead of unbounded.
- 2026-08-07, #2373 by @chr-hertel (Christopher Hertel): moved the loop into the agent's `Runner` behind `ToolExecutorInterface`; `AgentProcessor` removed.
- 2026-08-29, #2436 by @chr-hertel (Christopher Hertel): the loop is iterative, `maxToolCalls` bounds the whole call, and executors yield updates.
- 2026-09-15, #1829 by @DZunke (Denis Zunke): added `FiberToolExecutor` for concurrent tool calls.

## References

- `src/Agent.php`: the tool-related constructor arguments.
- `src/Execution/Runner.php`: the loop, tool restriction and metadata merging.
- `src/Toolbox/ToolExecutorInterface.php`, `src/Toolbox/SequentialToolExecutor.php`,
  `src/Toolbox/FiberToolExecutor.php`: the executors.
- `src/Toolbox/Event/ToolCallsExecuted.php`: the event that can end the loop.
- `src/Exception/MaxIterationsExceededException.php`: the iteration limit.
