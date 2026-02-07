CHANGELOG
=========

0.4
---

 * [BC BREAK] Rename `Symfony\AI\Agent\Toolbox\Tool\Agent` to `Symfony\AI\Agent\Toolbox\Tool\Subagent`
 * [BC Break] Agent Processor has `keepToolMessages` set to `true` now by default since its helps llms understand what was tried.

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
