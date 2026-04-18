CHANGELOG
=========

0.8
---

 * [BC BREAK] Change `public array $calls` to `private array $calls` in `TraceableChat` and `TraceableMessageStore` - use `getCalls()` instead

0.7
---

 * Add `TraceableChat` and `TraceableMessageStore` profiler decorators moved from AI Bundle
 * Add `ChatInterface::stream()` method for real-time streaming support

0.4
---

 * Add `ResetInterface` support to in-memory store

0.1
---

 * Add the component
 * Add `metadata` from `TextResult` to `AssistantMessage`
