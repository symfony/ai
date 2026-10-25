---
status: accepted
date: 2026-10-25
---

# 0001. A chat is a thin conversation layer over an agent and a message store

## Context and Problem Statement

An agent call has no memory of earlier calls: it takes a `MessageBag` and returns a
result. A chat needs the conversation to survive between HTTP requests or CLI turns, so
each turn must load the history, add the new user message, call the agent and save the
answer. Prompts, tools, memory and streaming already live in the Agent component and
should not be built again for chats. Storing messages also should not be the agent's
job, and an agent must work without any chat.

## Decision

`Chat` (`src/Chat.php`) implements `ChatInterface` with three methods and has no AI
logic of its own. It is built from an `AgentInterface` and a store that implements both
`MessageStoreInterface` and `ManagedStoreInterface`:

- `initiate(MessageBag)` drops the stored conversation and saves the given messages,
  usually the system prompt.
- `submit(UserMessage)` loads the bag, adds the user message, reads
  `$agent->call($messages)->getResult()`, turns it into an `AssistantMessage` with
  `Message::ofAssistant()` (adding the result metadata to the message), adds it and
  saves the whole bag. It returns the assistant message.
- `stream(UserMessage)` calls the agent with `['stream' => true]`, yields the text
  pieces of `Execution::asStream()`, and only after the stream ends builds the assistant
  message from the execution's result and saves the bag.

Errors come from the agent: `ChatInterface` documents `Agent\Exception\ExceptionInterface`.
`TraceableChat` wraps a chat for the profiler.

## Consequences

- **Good:** every agent feature (tools, memory, multi-agent) works in a chat without
  changes here, since any `AgentInterface` can be passed.
- **Good:** the component is small; storage is the only real extension point.
- **Trade-off:** the whole conversation is loaded and saved on every turn, so the cost
  grows with the history.
- **Trade-off:** a streamed answer is saved only when the consumer reads the generator
  to the end; an aborted stream leaves the user message unsaved.
- **Trade-off:** messages the agent adds during tool calling end up in the stored bag
  unless the agent is configured with `excludeToolMessages`.

## Alternatives Considered

- **Chat inside the Agent component**: the first design (php-llm/llm-chain#208). It was
  moved into its own component in #675, so new stores can be added without touching the
  agent, and agents can run without a chat.
- **A listener that saves streamed chunks (`ChatStreamListener`)**: added in #1583 and
  removed in #2436, because the agent's `Execution` already gives the text pieces and the
  final result, so `Chat::stream()` reads it directly.

## History

- 2025-07-04, #56 by @chr-hertel (Christopher Hertel): brought `Chat` and the message store over from php-llm/llm-chain#208, still inside the Agent component.
- 2025-10-03, #675 by @Guikingone (Guillaume Loulier): moved the chat into the new Chat component.
- 2025-11-07, #765 by @Guikingone (Guillaume Loulier): improved `TraceableChat` and `TraceableMessageStore` for the profiler.
- 2026-02-09, #1560 by @janssensglenn (Glenn Janssens): the assistant message keeps the result metadata.
- 2026-03-24, #1583 by @Guikingone (Guillaume Loulier): added `Chat::stream()`, first through stream result listeners.
- 2026-08-29, #2436 by @chr-hertel (Christopher Hertel): `Chat::stream()` reads the agent's `Execution`; `ChatStreamListener` removed.

## References

- `src/ChatInterface.php`: `initiate()`, `submit()`, `stream()`.
- `src/Chat.php`: load, call, save.
- `src/TraceableChat.php`: the profiling decorator.
- `CHANGELOG.md`: the 0.13 removal of `ChatStreamListener`.
