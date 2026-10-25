---
status: accepted
date: 2026-10-25
---

# 0005. Profiling through traceable decorators in debug mode

## Context and Problem Statement

Developers need to see what an AI feature did during a request: which model was called,
which tools ran, what was stored. This belongs in the Symfony profiler, but production
should not pay for it, and the components should not depend on the profiler.

## Decision

The components ship `Traceable*` decorators that record calls, such as
`TraceablePlatform`, `TraceableAgent`, `TraceableToolbox` and `TraceableStore`.

`DebugCompilerPass` runs only when `kernel.debug` is true. It wraps every tagged platform,
agent, toolbox, store, message store and chat in its traceable decorator, including
services the application registers and tags itself. The decorators are reset after each
request.

`Profiler\DataCollector` reads the recorded calls after the response is sent
(`lateCollect()`). This also captures calls made while a streamed response is sent, and it
does not slow the response down. `Test\AiAssertionsTrait` uses the same data for
assertions in functional tests. In production, the collector is removed.

## Consequences

- **Good:** production has no tracing code; in debug mode everything is traced without any
  configuration.
- **Good:** the profiler panel, the timeline and test assertions share one source of data.
- **Trade-off:** only services with the bundle's tags are traced; a service an application
  builds by hand must carry the tag (for example `ai.platform`) to show up.

## Alternatives Considered

- **Collecting only from component events**: not chosen, because the event dispatcher is
  optional in the components and the Chat component sends no events.
- **Decorating inside `loadExtension()`**: the approach before #1653. It only covered
  services the bundle created from its configuration, so hand-registered services were not
  traced.

## History

- 2024-09-21, fad354c1 by Christopher Hertel: first profiler, with traceable decorators for
  the model and the tool registry, active only when `kernel.debug` is on.
- 2025-07-20, #173 by @valtzu: `LateDataCollectorInterface`, because calls made inside a
  `StreamedResponse` were not collected, and stream results could not be stored.
- 2026-02-18, #1533 by @santysisi (Santiago San Martin): decorators reset between requests
  to prevent memory leaks.
- 2026-02-20, #1653 by @HypeMC: moved the decoration into `DebugCompilerPass`, so services
  that users register and tag themselves are traced too.
- 2026-04-05, #1872 by @chr-hertel (Christopher Hertel): moved the `Traceable*` decorators
  from the bundle into their components.
- 2026-07-12, #2310 by @chr-hertel (Christopher Hertel): `AiAssertionsTrait` for functional
  tests on the collected data.

## References

- `src/DependencyInjection/DebugCompilerPass.php`: wrapping services in decorators.
- `src/Profiler/DataCollector.php`: collecting the recorded data.
- `src/Test/AiAssertionsTrait.php`: assertions on that data.
