---
status: accepted
date: 2026-10-25
---

# 0005. Backends plug in as bridge packages under src/Bridge

## Context and Problem Statement

The component supports more than twenty backends, each with its own client library,
extension or HTTP API. Shipping all of them in `symfony/ai-store` would pull every
dependency into every application, while scattering them across separate repositories
would let them drift from the core contracts.

## Decision

Each backend lives in `src/Bridge/<Name>/` with its own `composer.json`, `README.md`,
`CHANGELOG.md`, `LICENSE`, tests and PSR-4 namespace `Symfony\AI\Store\Bridge\<Name>\`,
and is split into a package of type `symfony-ai-store` (for example
`symfony/ai-postgres-store`, listed in the component `README.md`). A bridge depends on
`symfony/ai-store` and `symfony/ai-platform` plus its own client requirements; the core
has none of them.

A bridge plugs in only by implementing the core contracts: its `Store` class implements
`StoreInterface` and, when it owns a schema, `ManagedStoreInterface` (ADR 0001). It reuses
the core exceptions (`InvalidArgumentException`, `RuntimeException`,
`UnsupportedQueryTypeException`) rather than defining its own. There is no registry in
the component; wiring stores by name is left to the integrating layer, and the commands
receive them through a `ServiceLocator`.

`InMemory\Store` stays in the core as the dependency-free reference implementation, and
`Test\AbstractStoreIntegrationTestCase` gives every bridge the same behavioral test
suite (setup, add, query by type, count, remove, clear, drop).

## Consequences

- **Good:** applications install only the backends they use.
- **Good:** bridges live in the monorepo next to the contracts, so a contract change and
  all bridge updates land together.
- **Good:** the shared integration test case keeps behavior consistent across backends.
- **Trade-off:** every bridge carries its own package metadata and tooling configuration (`phpunit.xml.dist`, `phpstan.dist.neon`) that
  must be kept in sync with the core version constraints.
- **Trade-off:** backend-specific capabilities only surface through `$options` arrays
  and constructor arguments, not through the shared interfaces.

## Alternatives Considered

- **All backends inside `symfony/ai-store`**: not chosen because of the dependency
  footprint.
- **Independent repositories per backend**: not chosen because contract changes would
  need coordinated releases across repositories.

## References

- `src/Bridge/`: one directory per backend.
- `src/Bridge/Postgres/composer.json`: package type, dependencies, autoload namespace.
- `src/Bridge/Postgres/Store.php`: a bridge implementing both store interfaces.
- `src/InMemory/Store.php`: the in-core reference store.
- `src/Test/AbstractStoreIntegrationTestCase.php`: shared bridge test suite.
- `README.md`: table of store bridge packages.
