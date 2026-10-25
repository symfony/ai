---
status: accepted
date: 2026-10-25
---

# 0002. The message store contract is split into storage and management

## Context and Problem Statement

Conversations are persisted in very different backends: process memory, a PSR-6 cache,
the HTTP session, Doctrine DBAL, Redis, MongoDB, Meilisearch and others. Some need
infrastructure (a table, an index) to be created before use, others need none. The chat
must work against all of them through one contract, and operators need a way to prepare
and clean up the infrastructure outside the request cycle.

## Decision

- `MessageStoreInterface` holds the conversation: `save(MessageBag)` and
  `load(): MessageBag`. It has no conversation identifier parameter; a store instance is
  bound to one conversation through its constructor (for example the `$identifier` of
  `InMemory\Store`, the `$cacheKey` of the Cache bridge, the `$sessionKey` of the Session
  bridge or the `$tableName` of the Doctrine bridge). `load()` returns an empty
  `MessageBag` when nothing was saved.
- `ManagedStoreInterface` manages the infrastructure: `setup(array $options = [])` and
  `drop()`. `Chat` requires `MessageStoreInterface&ManagedStoreInterface` and calls
  `drop()` in `initiate()`.
- The console commands `ai:message-store:setup` (`Command\SetupStoreCommand`) and
  `ai:message-store:drop` (`Command\DropStoreCommand`) run these methods on stores taken
  from a `ServiceLocator`, wrapping failures in `Exception\RuntimeException`.
- `InMemory\Store` ships with the component and also implements `ResetInterface`; every
  other backend is a bridge package under `src/Bridge/` implementing both interfaces.

## Consequences

- **Good:** a new backend is a single class with four methods; the chat never knows
  which backend it uses.
- **Good:** schema creation is an explicit operator step, not a side effect of the first
  request.
- **Trade-off:** multi-user applications must create or configure one store instance per
  conversation, since the contract carries no conversation id.
- **Trade-off:** `save()` receives the whole bag, and each bridge decides whether it
  replaces or appends; the contract does not specify it.

## Alternatives Considered

- **One interface with setup and drop included**: not chosen because reading and writing
  a conversation is a different concern from provisioning infrastructure, and the
  commands only need the management side.
- **A conversation id on every method**: not part of the current contract; scoping is
  left to the store's construction.

## References

- `src/MessageStoreInterface.php`, `src/ManagedStoreInterface.php`: the contracts.
- `src/Chat.php`: requires both interfaces.
- `src/InMemory/Store.php`: the built-in store.
- `src/Command/SetupStoreCommand.php`, `src/Command/DropStoreCommand.php`: operations.
- `src/Bridge/Cache/MessageStore.php`, `src/Bridge/Doctrine/DoctrineDbalMessageStore.php`:
  examples of bridges.
