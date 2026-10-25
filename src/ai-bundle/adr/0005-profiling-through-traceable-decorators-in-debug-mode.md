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
agent, toolbox, store, message store and chat in its traceable decorator. The decorators
are reset after each request.

`Profiler\DataCollector` reads the recorded calls after the response is sent
(`lateCollect()`), so it does not slow the response down. `Test\AiAssertionsTrait` uses the
same data for assertions in functional tests. In production, the collector is removed.

## Consequences

- **Good:** production has no tracing code; in debug mode everything is traced without any
  configuration.
- **Good:** the profiler panel, the timeline and test assertions share one source of data.
- **Trade-off:** only services created by the bundle are traced; services an application
  builds by hand do not show up.

## Alternatives Considered

- **Collecting only from component events**: not chosen, because the event dispatcher is
  optional in the components and the Chat component sends no events.

## References

- `src/DependencyInjection/DebugCompilerPass.php`: wrapping services in decorators.
- `src/Profiler/DataCollector.php`: collecting the recorded data.
- `src/Test/AiAssertionsTrait.php`: assertions on that data.
