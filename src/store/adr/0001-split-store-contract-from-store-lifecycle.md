---
status: accepted
date: 2026-10-25
---

# 0001. Split the store contract from the store lifecycle

## Context and Problem Statement

Every vector store must add, return, remove and count documents. Only some stores also
own a schema that the application can create and destroy: a Postgres table, a Qdrant
collection or an in-memory array can be set up and dropped, while a Supabase table is
created outside the application. As issue #285 put it, the component only needs
`setup()` and `drop()` when it should manage the store, not only use it. Putting them on
the main interface would force the other stores to fake or reject those calls.

## Decision

`StoreInterface` is the data contract every store implements: `add()`, `remove()`,
`clear()`, `query()`, `supports()` and, through `\Countable`, `count()`. `clear()`
deletes all documents but keeps the table, index or collection, so the store can be
filled again without `setup()`.

`ManagedStoreInterface` is a separate interface with `setup()` and `drop()`. It does not
extend `StoreInterface`. A store opts in by implementing both, as `InMemory\Store` and
most bridges (for example `Bridge\Postgres\Store`) do. `Bridge\Supabase\Store` implements
only `StoreInterface`.

Callers check the capability at runtime. `Command\SetupStoreCommand` and
`Command\DropStoreCommand` throw a `RuntimeException` ("does not support setup") when the
store is not managed. `TraceableStore` implements both and forwards `setup()`/`drop()`
only when the inner store is managed.

A store that cannot perform a data operation throws
`Exception\UnsupportedFeatureException`; `Test\AbstractStoreIntegrationTestCase` accepts
it for `remove()`.

## Consequences

- **Good:** a store without a lifecycle does not need empty or throwing `setup()`/`drop()`
  methods.
- **Good:** the `ai:store:setup`, `ai:store:drop` and `ai:store:clear` commands are
  separate from the data path, which indexers and retrievers use through
  `StoreInterface` only.
- **Trade-off:** lifecycle support is found with `instanceof`, so a missing capability
  shows up at runtime, not during static analysis.
- **Trade-off:** decorators such as `TraceableStore` must implement both interfaces and
  handle an inner store that is not managed.

## Alternatives Considered

- **One interface with all methods**: not chosen, because stores without a lifecycle
  would need meaningless implementations.
- **`ManagedStoreInterface extends StoreInterface`**: not chosen; the lifecycle is a
  separate capability that the commands use on its own.
- **`InitializableStoreInterface` plus `DroppableStoreInterface`**: the earlier design,
  one interface per method (`initialize()` since php-llm/llm-chain#51, `drop()` added
  with the Milvus store). Replaced by one interface with clear names in #328 and #336.
- **Separate write and query interfaces**: the first design split `StoreInterface`
  (add) from `VectorStoreInterface` (query). Merged into one interface in #317, because
  "we don't need to split into these two concerns in reality".

## History

- 2024-09-29, php-llm/llm-chain#51 by @OskarStark (Oskar Stark): added the optional `InitializableStoreInterface` with the MongoDB store.
- 2025-08-15, #317 by @chr-hertel (Christopher Hertel): merged the write and query interfaces into one `StoreInterface`.
- 2025-08-20, #328 by @Guikingone (Guillaume Loulier): replaced `InitializableStoreInterface` with `ManagedStoreInterface` (`setup()`, `drop()`), as agreed in issue #285; #336 by @OskarStark removed the old `DroppableStoreInterface`.
- 2025-09-05, #335 by @Guikingone (Guillaume Loulier): added the `ai:store:setup` and `ai:store:drop` commands.
- 2026-07-15, #2305 by @chr-hertel (Christopher Hertel): added `StoreInterface::clear()` and `ai:store:clear`, to empty a store without dropping it; the name follows PSR-6 and Doctrine `clear()`.
- 2026-09-19, #1755 by @lochmueller (Tim Lochmüller): `StoreInterface` now extends `\Countable`.

## References

- `src/StoreInterface.php`: the data contract, including `clear()` and `\Countable`.
- `src/ManagedStoreInterface.php`: `setup()` and `drop()`.
- `src/Command/SetupStoreCommand.php`, `src/Command/DropStoreCommand.php`: runtime check.
- `src/TraceableStore.php`: conditional forwarding of the lifecycle calls.
- `src/Bridge/Supabase/Store.php`: a store that is not managed.
- `src/Exception/UnsupportedFeatureException.php`, `src/Test/AbstractStoreIntegrationTestCase.php`.
