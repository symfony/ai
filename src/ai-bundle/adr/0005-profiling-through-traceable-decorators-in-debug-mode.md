---
status: accepted
date: 2026-10-25
---

# 0005. Profiling through traceable decorators in debug mode

## Context and Problem Statement

What an AI feature did during a request (which model was called with which input, which
tools ran, what was stored or retrieved) is hard to see from the outside, and model output
is not deterministic. Developers need this information in the Symfony profiler, but
production must not pay for recording it, and the components themselves should not depend
on the profiler.

## Decision

The components ship `Traceable*` decorators that record their calls: `TraceablePlatform`,
`TraceableAgent`, `TraceableToolbox`, `TraceableStore`, `TraceableMessageStore` and
`TraceableChat`. The bundle applies them in `DependencyInjection\DebugCompilerPass`, which
only runs when `kernel.debug` is true. For every service tagged `ai.platform`, `ai.agent`,
`ai.toolbox`, `ai.store`, `ai.message_store` and `ai.chat` it registers a decorator
`ai.traceable_<kind>.<suffix>` with priority `-1024`, so it wraps every other decorator, tags
it `ai.traceable_<kind>`, and tags it `kernel.reset` so recorded calls do not leak between
requests. Platform, agent and toolbox decorators also receive `debug.stopwatch` to feed the
performance timeline.

`Profiler\DataCollector` (`ai.data_collector`) receives the decorators as tagged iterators.
It implements `LateDataCollectorInterface` and gathers the data in `lateCollect()`, after the
response is sent, so it can await streamed and asynchronous platform results without
delaying the response. Toolboxes tagged `ai.profiler_toolbox` provide the list of available
tools; for MCP servers that list goes through `Profiler\DeferredToolbox`, so the profiler does
not open a connection just to list tools. Outside debug mode `AiBundle::loadExtension()`
removes `ai.data_collector` and `ai.traceable_toolbox`.

`Test\AiAssertionsTrait` builds functional-test assertions (platform calls, tool calls,
token usage) on the same collected data.

## Consequences

- **Good:** production containers contain no tracing code paths; debug containers trace
  every configured instance without any application configuration.
- **Good:** one recording mechanism serves the profiler panel, the timeline and functional
  tests.
- **Trade-off:** only services that carry the bundle's tags are traced; a platform or store
  an application builds by hand does not show up in the profiler.
- **Trade-off:** decorators keep every call of a request in memory until reset, which is
  acceptable in debug mode but large for long batch commands run with debug enabled.

## Alternatives Considered

- **Collecting from component events only**: not chosen because the event dispatcher is
  optional in the components (for example the nullable `$eventDispatcher` of `Toolbox`) and
  the Chat component dispatches no events, while a decorator records every call of a tagged
  service.
- **Always-on tracing**: not chosen because recording inputs and results costs memory and
  time on every production request.

## References

- `src/DependencyInjection/DebugCompilerPass.php`: decorator registration per tag.
- `src/Profiler/DataCollector.php`: late collection from the traceable decorators.
- `src/Profiler/DeferredToolbox.php`: tool listing for MCP toolboxes without connecting.
- `src/Test/AiAssertionsTrait.php`: assertions on collected profiler data.
- `config/services.php`: `ai.data_collector` and its tagged iterators.
