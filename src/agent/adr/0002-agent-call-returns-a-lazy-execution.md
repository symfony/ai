---
status: accepted
date: 2026-10-25
---

# 0002. An agent call returns a lazy execution that is also its result

## Context and Problem Statement

A single agent call can involve several model requests, tool calls and streamed deltas.
Callers have different needs: most only want the final answer, a UI wants to show
progress or stream text, and long-running calls must be cancelable. Returning a plain
`ResultInterface` hides everything between request and answer, while a separate
streaming or event API per use case would multiply the entry points of the agent.

## Decision

`AgentInterface::call(string|MessageBag|UserMessage $input, array $options = [])` returns
an `Execution\Execution` (`src/Execution/Execution.php`):

- The execution is lazy. It wraps a generator factory and nothing runs until it is
  consumed; consuming drives the agent including its side effects.
- It implements `ResultInterface`, so `getContent()`, `getResult()` and `getMetadata()`
  drive it to completion, and the typed accessors of `TypedResultTrait` (`asText()`,
  `asObject()`, `asStream()`, ...) work as on a platform result.
- It is an `IteratorAggregate` of `Execution\UpdateInterface`: `Update\Progress` updates
  carry a stage (`Progress::STAGE_MODEL_REQUEST`, `STAGE_DELTA`, `STAGE_TOOL_CALL`,
  `STAGE_HANDOFF`) and a payload, and the last update is an `Update\Result`. Callbacks can
  be registered with `onProgress()` and `onResult()`.
- With the `stream` option, `getContent()` and `asStream()` yield the deltas of the
  answer; there is no separate streaming method on the agent.
- `cancel()` stops the execution and cancels the active HTTP response through
  `Execution\Cancellation`.
- The final result is cached; re-iterating a consumed execution throws.

The input is normalized to a `MessageBag` by `InputNormalizer`, so a plain string works.

## Consequences

- **Good:** one method serves the eager, the observing and the streaming use case, and
  decorators (`TraceableAgent`, `MultiAgent`, `SpeechAgent`) forward progress by wrapping
  the inner execution.
- **Good:** progress is observable without an event dispatcher.
- **Trade-off:** forgetting to consume the execution means the agent never runs, which
  surprises callers used to eager calls.
- **Trade-off:** an execution can be consumed only once; a retry needs a new `call()`.

## Alternatives Considered

- **Return the platform result directly (before 0.13)**: replaced because progress, tool
  calls and streamed deltas inside the tool loop were not observable, and streaming
  needed the separate `Toolbox\StreamListener`.
- **Callbacks or events only**: not chosen because pull-based iteration composes with
  generators and works for nested agents without shared global state.

## References

- `src/AgentInterface.php`: the `call()` contract.
- `src/Execution/Execution.php`: the lazy execution and its accessors.
- `src/Execution/Update/Progress.php`, `src/Execution/Update/Result.php`: the updates.
- `src/Execution/Cancellation.php`: cancellation of the active response.
- `src/InputNormalizer.php`: input normalization.
- `CHANGELOG.md`: the 0.13 and 0.14 entries introducing `Execution` and `cancel()`.
