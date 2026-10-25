---
status: accepted
date: 2026-10-25
---

# 0002. The message store contract is split into storage and management

## Context and Problem Statement

Conversations are stored in very different backends: process memory, a PSR-6 cache, the
HTTP session, Doctrine DBAL, Redis, MongoDB, Meilisearch and others. Some need
infrastructure (a table, an index) before use, others need none. The chat must work with
all of them through one contract, and operators need a way to create and remove that
infrastructure outside the request cycle.

## Decision

- `MessageStoreInterface` holds the conversation: `save(MessageBag)` and
  `load(): MessageBag`. It takes no conversation id; a store instance is tied to one
  conversation through its constructor (for example the `$identifier` of
  `InMemory\Store`, the `$cacheKey` of the Cache bridge, the `$sessionKey` of the Session
  bridge or the `$tableName` of the Doctrine bridge). `load()` returns an empty
  `MessageBag` when nothing was saved.
- `ManagedStoreInterface` manages the infrastructure: `setup(array $options = [])` and
  `drop()`. `Chat` requires `MessageStoreInterface&ManagedStoreInterface` and calls
  `drop()` in `initiate()`.
- The console commands `ai:message-store:setup` (`Command\SetupStoreCommand`) and
  `ai:message-store:drop` (`Command\DropStoreCommand`) run these methods on stores from a
  `ServiceLocator` and wrap failures in `Exception\RuntimeException`.
- `InMemory\Store` ships with the component and also implements `ResetInterface`. Every
  other backend is a bridge package under `src/Bridge/` that implements both interfaces.

The same split exists for vector stores in the Store component (#328), which this contract
follows.

## Consequences

- **Good:** a new backend is one class with four methods; the chat never knows which
  backend it uses.
- **Good:** creating the schema is an explicit operator step, not a side effect of the
  first request.
- **Trade-off:** multi-user applications must create or configure one store instance per
  conversation, since the contract has no conversation id.
- **Trade-off:** `save()` gets the whole bag, and each bridge decides whether it replaces
  or appends; the contract does not say.

## Alternatives Considered

- **`clear()` on `MessageStoreInterface`**: the first contract (php-llm/llm-chain#208)
  had `save()`, `load()` and `clear()`. #725 replaced `clear()` with
  `ManagedStoreInterface::drop()` and added `setup()`, so console commands could manage
  stores.

## History

- 2025-07-04, #56 by @chr-hertel (Christopher Hertel): first `MessageStoreInterface` with `save()`, `load()` and `clear()`, and in-memory, cache and session stores.
- 2025-10-06, #725 by @Guikingone (Guillaume Loulier): added `ManagedStoreInterface`, following the Store component's interface of the same name.
- 2025-10-07, #734 by @Guikingone (Guillaume Loulier): added the `ai:message-store:setup` and `ai:message-store:drop` commands.
- 2025-10-20, #239, #788, #797 and #907 by @Guikingone (Guillaume Loulier): added Meilisearch, Pogocache, Redis and MongoDB stores as bridges (merged until 2025-11-22).

## References

- `src/MessageStoreInterface.php`, `src/ManagedStoreInterface.php`: the contracts.
- `src/Chat.php`: requires both interfaces.
- `src/InMemory/Store.php`: the built-in store.
- `src/Command/SetupStoreCommand.php`, `src/Command/DropStoreCommand.php`: operations.
- `src/Bridge/Cache/MessageStore.php`, `src/Bridge/Doctrine/DoctrineDbalMessageStore.php`:
  examples of bridges.
