---
status: accepted
date: 2026-10-25
---

# 0002. Predictable service ids and named autowiring aliases

## Context and Problem Statement

An application often configures several instances of the same contract: two platforms,
several agents, a store per index. Application services need a way to pick one of them
without knowing how the bundle built it, and configuration entries need a way to reference
each other (an agent naming its platform, an indexer naming its store).

## Decision

Every configured instance gets a service id derived from its configuration key:
`ai.platform.<type>`, `ai.agent.<name>`, `ai.store.<type>.<name>`,
`ai.message_store.<type>.<name>`, `ai.indexer.<name>`, `ai.retriever.<name>`,
`ai.platform.job_client.<type>`. Configuration options that point to another service take
such an id (for example `ai.agent.<name>.platform`, which defaults to
`PlatformInterface::class`).

For each instance the bundle calls `registerAliasForArgument()` on its contract, so it can be
injected by argument name or with `#[Target]`:

- each platform tagged `ai.platform` under its tag name, for `PlatformInterface`;
- each agent under its name, for `AgentInterface`;
- each store and message store under both `<name>` and `<type>_<name>`, for
  `StoreInterface` and `MessageStoreInterface`;
- each job client under its platform type, for `JobClientInterface`.

When exactly one instance of a contract is configured, the interface itself
(`PlatformInterface`, `AgentInterface`, `StoreInterface`, `MessageStoreInterface`,
`ChatInterface`, `IndexerInterface`, `RetrieverInterface`) is aliased to it, so plain
autowiring works without a name.

## Consequences

- **Good:** application code depends only on the component interfaces and selects an
  instance with `#[Target('name')]` or a matching argument name.
- **Good:** service ids are stable and readable, so other bundles and compiler passes (for
  example `DebugCompilerPass`) can find instances by tag and decorate them.
- **Trade-off:** adding a second instance removes the unnamed interface alias, so code that
  relied on plain autowiring has to name its target.
- **Trade-off:** the ids are part of the public surface; renaming a configuration key renames
  the service.

## Alternatives Considered

- **Requiring explicit service references everywhere**: not chosen because it makes the
  common single-instance case verbose.
- **Registry or locator services keyed by name**: not chosen because they hide the
  dependency from the container and from autowiring.

## References

- `src/AiBundle.php`: `loadExtension()` (platform aliases and single-instance aliases),
  `processAgentConfig()`, `processStoreConfig()`, `processMessageStoreConfig()`.
- `config/options.php`: the `platform` option of an agent defaulting to `PlatformInterface`.
- `CHANGELOG.md`: 0.15, "Register a `#[Target]` alias for every configured platform".
