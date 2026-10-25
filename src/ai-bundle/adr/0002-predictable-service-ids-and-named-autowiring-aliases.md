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
by argument name or with `#[Target]`. The alias is the plain name, without a suffix such as
`Agent`. Stores and message stores are available as `<name>` and as `<type>_<name>`, so
two stores of different types can share a name.

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
- **Aliases with a suffix, such as `$fooAgent`**: introduced in #281 to avoid clashing with
  other services named `foo`, and removed in 0.2 (#1285) as a BC break.

## History

- 2024-12-13, php-llm/llm-chain-bundle#42 by Christopher Hertel: ids like
  `llm_chain.platform.<type>`, `llm_chain.chain.<name>` and `llm_chain.store.<type>.<name>`,
  with the interface aliased only when exactly one instance is tagged.
- 2025-07-17, #148 by @OskarStark (Oskar Stark): renamed the extension from `symfony_ai` to
  `ai`, which gives today's `ai.*` ids.
- 2025-08-12, #281 by @Guikingone (Guillaume Loulier): named argument aliases for agents,
  so several agents can be injected by name.
- 2025-09-23, #641 by @Guikingone (Guillaume Loulier): the extra `<type>_<name>` store alias,
  so stores of different types can use the same name.
- 2025-12-28, #1285 by @bigdevlarry (larry.sulebalogun): removed the suffix from the
  aliases.
- 2026-10-19, #2643 by @Didixdan (Alexandre Varin): an alias for every platform; before,
  most platforms had none, so `#[Target]` did not work for them.

## References

- `src/AiBundle.php`: `loadExtension()`, `processAgentConfig()`, `processStoreConfig()`,
  `processMessageStoreConfig()`.
- `CHANGELOG.md`: 0.15, a `#[Target]` alias for every configured platform.
