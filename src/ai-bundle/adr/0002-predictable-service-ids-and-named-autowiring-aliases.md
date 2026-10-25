---
status: accepted
date: 2026-10-25
---

# 0002. Predictable service ids and named autowiring aliases

## Context and Problem Statement

An application often has several instances of the same kind: two platforms, several
agents, one store per index. Application code needs a simple way to pick one, and
configuration entries need a way to point to each other.

## Decision

Each configured instance gets a service id built from its configuration key:
`ai.platform.<type>`, `ai.agent.<name>`, `ai.store.<type>.<name>`,
`ai.message_store.<type>.<name>`, `ai.indexer.<name>` and `ai.retriever.<name>`. Options
that point to another service use these ids.

The bundle also calls `registerAliasForArgument()` for each instance, so it can be injected
by argument name or with `#[Target]`. Stores and message stores are available as `<name>`
and as `<type>_<name>`.

When only one instance of a kind exists, the interface itself (`PlatformInterface`,
`AgentInterface`, `StoreInterface`, and so on) points to it, so plain autowiring works.

## Consequences

- **Good:** application code depends only on interfaces and picks an instance with
  `#[Target('name')]`.
- **Good:** other code can find instances by id or tag, for example to decorate them.
- **Trade-off:** with a second instance there is no plain interface alias any more, so code
  must name the instance it wants.
- **Trade-off:** renaming a configuration key renames the service.

## Alternatives Considered

- **Always requiring explicit service references**: not chosen, because the common case of
  one instance would become verbose.

## References

- `src/AiBundle.php`: `loadExtension()`, `processAgentConfig()`, `processStoreConfig()`,
  `processMessageStoreConfig()`.
- `CHANGELOG.md`: 0.15, a `#[Target]` alias for every configured platform.
