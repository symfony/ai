---
status: accepted
date: 2026-10-25
---

# 0005. Backends plug in as bridge packages under src/Bridge

## Context and Problem Statement

The component supports more than twenty backends, each with its own client library,
PHP extension or HTTP API. Shipping all of them in `symfony/ai-store` would pull every
dependency into every application. Keeping them in separate repositories would let them
drift away from the core contracts.

## Decision

Each backend lives in `src/Bridge/<Name>/` with its own `composer.json`, `README.md`,
`CHANGELOG.md`, `LICENSE`, tests and PSR-4 namespace `Symfony\AI\Store\Bridge\<Name>\`.
It is split into a read-only package of type `symfony-ai-store` (for example
`symfony/ai-postgres-store`, listed in the component `README.md`), the same way tools
are split in the Agent component. A bridge depends on `symfony/ai-store` and
`symfony/ai-platform` plus its own client libraries; the core needs none of them.

A bridge plugs in only by implementing the core contracts: its `Store` class implements
`StoreInterface` and, when it owns a schema, `ManagedStoreInterface` (ADR 0001). It
reuses the core exceptions (`InvalidArgumentException`, `RuntimeException`,
`UnsupportedQueryTypeException`) instead of defining its own. The component has no
registry; wiring stores by name is left to the integrating layer, and the commands
receive them through a `ServiceLocator`.

`InMemory\Store` stays in the core as the reference implementation without
dependencies; it is not a bridge to an external service. `Test\AbstractStoreIntegrationTestCase`
gives every bridge the same behavior test suite (setup, add, query by type, count,
remove, clear, drop).

## Consequences

- **Good:** applications install only the backends they use.
- **Good:** bridges live in the monorepo next to the contracts, so a contract change and
  all bridge updates land in one pull request (as #2305 did for `clear()` in 25 stores).
- **Good:** the shared integration test case keeps behavior the same across backends
  and has already found real bugs, such as a broken `remove()` in Manticore Search
  (#2305).
- **Trade-off:** every bridge carries its own package metadata and tool configuration
  (`phpunit.xml.dist`, `phpstan.dist.neon`) that must stay in line with the core
  version constraints.
- **Trade-off:** backend-specific features only show up through `$options` arrays and
  constructor arguments, not through the shared interfaces.

## Alternatives Considered

- **All backends inside `symfony/ai-store`**: the setup until December 2025, with the
  bridges as namespaces of one package. Replaced by split packages in #1049 and #1057,
  because of the dependency footprint.
- **Independent repositories per backend**: not chosen, because a contract change would
  need coordinated releases across repositories.

## History

- 2024-08-25, 0a65c502 by Christopher Hertel: added the first store (ChromaDB) behind a shared `StoreInterface`; more backends followed in the same package.
- 2025-12-07, #1049 by @OskarStark (Oskar Stark): split the ChromaDB store into its own package, as a blueprint.
- 2025-12-07, #1057 by @OskarStark (Oskar Stark): split all remaining stores into dedicated packages.
- 2025-12-07, #1071 by @OskarStark (Oskar Stark): moved `InMemory\Store` out of `Bridge/` into the core, since it is not a bridge to an external service.
- 2026-02-13, #1563 by @chr-hertel (Christopher Hertel): added `AbstractStoreIntegrationTestCase` as a shared conformance test, moving store checks out of `examples/commands/store.php`.

## References

- `src/Bridge/`: one directory per backend.
- `src/Bridge/Postgres/composer.json`: package type, dependencies, autoload namespace.
- `src/Bridge/Postgres/Store.php`: a bridge implementing both store interfaces.
- `src/InMemory/Store.php`: the in-core reference store.
- `src/Test/AbstractStoreIntegrationTestCase.php`: shared bridge test suite.
- `README.md`: table of store bridge packages.
