---
status: accepted
date: 2026-10-25
---

# 0003. The agent drives the tool-calling loop through a replaceable executor

## Context and Problem Statement

Function calling is a loop: the model asks for tools, the tools run, their results are
fed back as messages and the model is invoked again until it answers. Until 0.13 this
loop lived in `Toolbox\AgentProcessor`, an input and output processor pair that
re-invoked the platform from inside the output phase, with streamed tool calls handled
by a separate `StreamListener`. That made streaming, progress reporting and
cancellation inside the loop hard to get right.

## Decision

The loop is part of the agent itself. `Agent` accepts `toolbox`, `toolExecutor`,
`maxToolCalls` (default `50`), `excludeToolMessages`, `includeSources` and
`eventDispatcher` and hands them to the internal `Execution\Runner`, which:

- exposes the toolbox's tools to the model through the `tools` option, narrowed to the
  names given in a per-call `tools` option, and rejects calls to registered tools that
  were not allowed;
- invokes the model, consumes a streamed round itself (forwarding deltas as `Progress`),
  and stops when the result contains no tool calls;
- delegates running the requested calls to `Toolbox\ToolExecutorInterface`, whose
  `execute(array $toolCalls)` is a generator that yields updates and returns one
  `ToolResult` per call in order. The default is `SequentialToolExecutor`;
  `FiberToolExecutor` runs calls concurrently;
- appends the assistant message and one tool call message per result, converted by
  `ToolResultConverter`, then dispatches `Toolbox\Event\ToolCallsExecuted`, whose
  listener may short-circuit the loop with a final result;
- throws `Exception\MaxIterationsExceededException` when `maxToolCalls` rounds are
  exceeded, and merges the metadata of all rounds into the final result.

## Consequences

- **Good:** streamed and unstreamed tool rounds go through the same loop, and every model
  request and tool call is visible as a `Progress` update of the execution.
- **Good:** how calls are run (sequentially, concurrently, remotely) is replaceable
  without touching the loop, since executors do not build messages.
- **Trade-off:** `Agent` has a long constructor that mixes the pipeline with tool
  settings.
- **Trade-off:** without `excludeToolMessages`, the caller's `MessageBag` grows with the
  intermediate tool messages, which a chat persists.

## Alternatives Considered

- **Keep the loop in `Toolbox\AgentProcessor`**: removed in 0.13 because a processor
  cannot yield progress or streamed deltas from inside the loop.
- **Let the toolbox execute the whole batch**: not chosen because the toolbox contract
  is a single call; scheduling belongs to the executor.

## References

- `src/Agent.php`: the tool-related constructor arguments.
- `src/Execution/Runner.php`: the iterative loop, tool restriction and metadata merging.
- `src/Toolbox/ToolExecutorInterface.php`, `src/Toolbox/SequentialToolExecutor.php`,
  `src/Toolbox/FiberToolExecutor.php`: the executors.
- `src/Toolbox/Event/ToolCallsExecuted.php`: the short-circuit event.
- `src/Exception/MaxIterationsExceededException.php`: the iteration limit.
