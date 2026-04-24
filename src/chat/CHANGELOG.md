CHANGELOG
=========

0.9
---

* [BC BREAK] `MessageNormalizer` now serializes assistant messages with an ordered `parts` field to preserve the sequence of `Text`, `Thinking`, and `ToolCall` blocks across round-trips. Legacy payloads (no `parts`, only `content` + `toolsCalls`) still denormalize, but ordering is no longer guaranteed for them.

0.8
---

 * [BC BREAK] Change `public array $calls` to `private array $calls` in `TraceableChat` and `TraceableMessageStore` - use `getCalls()` instead
 * Fix `MessageNormalizer::denormalize()` crash on `AssistantMessage`/`ToolCallMessage` payloads containing tool calls when the outer serializer chain does not include `ToolCallNormalizer`; denormalization now accepts both the OpenAI-style wrapped shape and the flat shape produced by the default `ObjectNormalizer` fallback

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
