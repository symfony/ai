---
status: accepted
date: 2026-10-25
---

# 0001. A chat is a thin conversation layer over an agent and a message store

## Context and Problem Statement

An agent call is stateless: it takes a `MessageBag` and returns a result. A chat needs
the conversation to survive between HTTP requests or CLI turns, so every turn must load
the history, add the new user message, call the agent and persist the answer. Prompting,
tools, memory and streaming already live in the Agent component and should not be
reimplemented for chats.

## Decision

`Chat` (`src/Chat.php`) implements `ChatInterface` with three methods and owns no AI
logic of its own. It is built from an `AgentInterface` and a store that implements both
`MessageStoreInterface` and `ManagedStoreInterface`:

- `initiate(MessageBag)` drops the stored conversation and saves the given messages,
  typically the system prompt.
- `submit(UserMessage)` loads the bag, adds the user message, reads
  `$agent->call($messages)->getResult()`, turns it into an `AssistantMessage` with
  `Message::ofAssistant()` (merging the result metadata into the message), adds it and
  saves the whole bag. It returns the assistant message.
- `stream(UserMessage)` calls the agent with `['stream' => true]`, yields the deltas of
  `Execution::asStream()`, and only after the stream is exhausted builds the assistant
  message from the execution's result and saves the bag.

Errors are the agent's: `ChatInterface` documents `Agent\Exception\ExceptionInterface`.
`TraceableChat` decorates a chat for the profiler.

## Consequences

- **Good:** every agent feature (tools, memory, multi-agent) is available in a chat
  without changes to this component, since any `AgentInterface` can be passed.
- **Good:** the component is small; persistence is the only real extension point.
- **Trade-off:** the full conversation is loaded and saved on every turn, so its cost
  grows with the history.
- **Trade-off:** a streamed answer is persisted only when the consumer drains the
  generator; an aborted stream leaves the user message unsaved.
- **Trade-off:** messages the agent appends during tool calling end up in the stored bag
  unless the agent is configured with `excludeToolMessages`.

## Alternatives Considered

- **A listener that persists streamed chunks (`ChatStreamListener`)**: removed in 0.13
  because the agent's `Execution` already exposes the deltas and the final result, so
  `Chat::stream()` consumes it directly.
- **Persisting inside an agent output processor**: not chosen because it would couple the
  agent to a storage concept and would not cover the user message of a failed turn.

## References

- `src/ChatInterface.php`: `initiate()`, `submit()`, `stream()`.
- `src/Chat.php`: load, call, persist.
- `src/TraceableChat.php`: the profiling decorator.
- `CHANGELOG.md`: the 0.13 removal of `ChatStreamListener`.
