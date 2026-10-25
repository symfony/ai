---
status: accepted
date: 2026-10-25
---

# 0001. Split the store contract from the store lifecycle

## Context and Problem Statement

Every vector store must accept, return, remove and count documents, but only some of
them own a schema that can be created and destroyed: a Postgres table, a Qdrant
collection or an in-memory array can be set up and dropped, while a Supabase table is
provisioned outside the application. Putting `setup()` and `drop()` on the main
interface would force stores without a lifecycle to fake or reject those calls.

## Decision

`StoreInterface` is the data contract every store implements: `add()`, `remove()`,
`clear()`, `query()`, `supports()` and, by extending `\Countable`, `count()`. `clear()`
empties the store while keeping its table, index or collection usable.

`ManagedStoreInterface` is a separate, independent interface with `setup()` and `drop()`.
It does not extend `StoreInterface`; a store opts in by implementing both, as
`InMemory\Store` and most bridges (for example `Bridge\Postgres\Store`) do, while
`Bridge\Supabase\Store` implements `StoreInterface` only.

Callers check the capability at runtime: `Command\SetupStoreCommand` and
`Command\DropStoreCommand` throw a `RuntimeException` ("does not support setup") when the
store is not a `ManagedStoreInterface`. `TraceableStore` implements both and forwards
`setup()`/`drop()` only when the decorated store is managed.

A store that cannot perform a data operation at all signals it with
`Exception\UnsupportedFeatureException`; `Test\AbstractStoreIntegrationTestCase` accepts
it for `remove()`.

## Consequences

- **Good:** a store without a lifecycle is not forced to implement no-op or throwing
  `setup()`/`drop()` methods.
- **Good:** schema management (`ai:store:setup`, `ai:store:drop`) is decoupled from the
  data path used by indexers and retrievers, which only depend on `StoreInterface`.
- **Trade-off:** lifecycle support is discovered with `instanceof`, so a missing
  capability surfaces at runtime, not at type-check time.
- **Trade-off:** decorators such as `TraceableStore` have to implement both interfaces
  and decide what to do when the inner store is not managed.

## Alternatives Considered

- **One interface with all methods**: not chosen because stores without a lifecycle
  would need meaningless implementations.
- **`ManagedStoreInterface extends StoreInterface`**: not chosen; the lifecycle is an
  orthogonal capability that commands consume on its own.

## References

- `src/StoreInterface.php`: the data contract, including `clear()` and `\Countable`.
- `src/ManagedStoreInterface.php`: `setup()` and `drop()`.
- `src/Command/SetupStoreCommand.php`, `src/Command/DropStoreCommand.php`: runtime check.
- `src/TraceableStore.php`: conditional forwarding of the lifecycle calls.
- `src/Bridge/Supabase/Store.php`: a store that is not managed.
- `src/Exception/UnsupportedFeatureException.php`, `src/Test/AbstractStoreIntegrationTestCase.php`.
