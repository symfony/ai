---
status: accepted
date: 2026-10-25
---

# 0002. An agent call returns a lazy execution that is also its result

## Context and Problem Statement

One agent call can include several model requests, tool calls and streamed text pieces.
Callers need different things: most want only the final answer, a UI wants to show
progress or stream text, and long calls must be cancelable. A plain `ResultInterface`
hides everything between request and answer. A separate streaming or event API for each
need would add more entry points to the agent.

## Decision

`AgentInterface::call(string|MessageBag|UserMessage $input, array $options = [])` returns
an `Execution\Execution` (`src/Execution/Execution.php`):

- The execution is lazy. Nothing runs until it is consumed; consuming it runs the agent,
  including its side effects.
- It implements `ResultInterface`. `getContent()`, `getResult()` and `getMetadata()` run
  it to the end, and the typed accessors of `TypedResultTrait` (`asText()`, `asObject()`,
  `asStream()`, ...) work as on a platform result.
- It is an `IteratorAggregate` of `Execution\UpdateInterface`. `Update\Progress` updates
  carry a stage (`Progress::STAGE_MODEL_REQUEST`, `STAGE_DELTA`, `STAGE_TOOL_CALL`,
  `STAGE_HANDOFF`) and a payload; the last update is an `Update\Result`. Callbacks can be
  registered with `onProgress()` and `onResult()`.
- With the `stream` option, `getContent()` and `asStream()` yield the text pieces of the
  answer. There is no separate streaming method.
- `cancel()` stops the execution and cancels the active HTTP response through
  `Execution\Cancellation`.
- The final result is cached. Iterating a consumed execution again throws.

`InputNormalizer` turns the input into a `MessageBag`, so a plain string works.

## Consequences

- **Good:** one method serves the simple, the observing and the streaming use case.
  Decorators (`TraceableAgent`, `MultiAgent`, `SpeechAgent`) pass progress on by
  wrapping the inner execution.
- **Good:** progress can be watched without an event dispatcher.
- **Trade-off:** the call is lazy, so side effects and exceptions only happen when the
  execution is consumed. A caller that never reads it never runs the agent, and type
  checks on the result need `getResult()` first.
- **Trade-off:** an execution can be consumed only once; a retry needs a new `call()`.

## Alternatives Considered

- **Return the platform result directly**: the design until #2436. Progress, tool calls
  and streamed text inside the tool loop were not visible, and streaming needed the
  separate `Toolbox\StreamListener` added in #1394. Both were replaced by `Execution`.

## History

- 2026-01-15, #1394 by @chr-hertel (Christopher Hertel): added stream listeners as the hook into streamed results, later replaced by `Execution`.
- 2026-07-22, #2353 by @chr-hertel (Christopher Hertel): first step of the agent rework; `call()` accepts a string or a `UserMessage` as input.
- 2026-08-29, #2436 by @chr-hertel (Christopher Hertel): `call()` returns a lazy `Execution` with progress updates and typed accessors; `StreamListener` removed.
- 2026-09-04, #2471 by @fabpot (Fabien Potencier): added `cancel()`, mainly for fibers and signal handling.
- 2026-09-05, #2483 by @saifulferoz: `MultiAgent` and `SpeechAgent` forward the progress of the agent they delegate to.
- 2026-10-06, #2619 by @wachterjohannes (Johannes Wachter): added the `Progress::STAGE_*` constants.

## References

- `src/AgentInterface.php`: the `call()` contract.
- `src/Execution/Execution.php`: the lazy execution and its accessors.
- `src/Execution/Update/Progress.php`, `src/Execution/Update/Result.php`: the updates.
- `src/Execution/Cancellation.php`: cancellation of the active response.
- `src/InputNormalizer.php`: input normalization.
- `CHANGELOG.md`: the 0.13 and 0.14 entries introducing `Execution` and `cancel()`.
