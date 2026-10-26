CHANGELOG
=========

0.16
----

 * Deprecate the component, use the `$messageStore` argument of `Symfony\AI\Agent\Agent` and the message stores of the Agent component instead
 * Add `AgentMessageStore`, keeping the conversation of a chat in a message store of the Agent component
 * [BC BREAK] Move the message store bridges to the Agent component, see `Symfony\AI\Agent\Bridge\*\MessageStore`
 * [BC BREAK] Remove `Command\SetupStoreCommand`, `Command\DropStoreCommand` and `TraceableMessageStore` in favor of `Symfony\AI\Agent\Command\SetupMessageStoreCommand`, `Symfony\AI\Agent\Command\DropMessageStoreCommand` and `Symfony\AI\Agent\MessageStore\TraceableMessageStore`
 * [BC BREAK] Remove `MessageNormalizer` in favor of `Symfony\AI\Platform\Serializer\MessageNormalizer`
 * [BC BREAK] The message store bridges keep the type of objects in the message metadata, their default serializer throws a `NotNormalizableValueException` for objects without a normalizer

0.13
----

 * [BC BREAK] Remove `ChatStreamListener`; `Chat::stream()` consumes the agent's execution itself and persists the streamed answer once it is complete

0.9
---

* [BC BREAK] `MessageNormalizer` now serializes assistant messages with an ordered `parts` field to preserve the sequence of `Text`, `Thinking`, and `ToolCall` blocks across round-trips. Legacy payloads (no `parts`, only `content` + `toolsCalls`) still denormalize, but ordering is no longer guaranteed for them.

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
