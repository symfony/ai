---
status: accepted
date: 2026-10-25
---

# 0001. One semantic configuration tree with optional bridge packages

## Context and Problem Statement

The bundle integrates the Platform, Agent, Store and Chat components and dozens of
platform, store and message-store bridges. Each bridge is its own Composer package with its
own dependencies, and an application installs only the few it uses. The bundle needs one
way to configure all of them that still works when most bridges are absent.

## Decision

The bundle is an `AbstractBundle` (`src/AiBundle.php`) that defines one semantic
configuration tree under the `ai` key. `config/options.php` declares the top-level sections
(`platform`, `model`, `agent`, `multi_agent`, `store`, `message_store`, `chat`,
`vectorizer`, `indexer`, `retriever`) and appends one node per bridge from a fragment file:
`config/platform/<type>.php`, `config/store/<type>.php` and `config/message_store/<type>.php`.

`AiBundle::loadExtension()` walks the processed configuration and turns each entry into
service definitions in `processPlatformConfig()`, `processStoreConfig()`,
`processMessageStoreConfig()` and the other `process*Config()` methods. A bridge service is
built through the bridge's own factory (for example `OpenAi\Factory::createPlatform` or a
store's `StoreFactory`), marked lazy, and tagged (`ai.platform`, `ai.store`,
`ai.message_store`).

Only `symfony/ai-platform` is a hard requirement in `composer.json`; the components and all
bridges are dev dependencies. Before wiring a section or a bridge, the bundle checks
`ContainerBuilder::willBeAvailable()` and throws a `RuntimeException` naming the
`composer require` command when the package is missing. Services that belong to an
uninstalled component (the commands, toolbox and listeners) are removed at the end of
`loadExtension()`.

## Consequences

- **Good:** an application configures every platform, store and agent in one YAML tree that
  Symfony validates, documents (`config:dump-reference`) and autocompletes.
- **Good:** installing the bundle pulls in no bridge; a misconfiguration fails at container
  build time with an actionable message.
- **Trade-off:** every bridge adds a fragment in `config/` and a branch in `AiBundle.php`, so
  the bundle class is large and changes with every new bridge.
- **Trade-off:** a bridge's options are duplicated between its factory signature and its
  configuration fragment and have to be kept in sync.

## Alternatives Considered

- **One bundle per bridge**: not chosen because it multiplies packages and leaves no single
  place to reference a platform from an agent or a store from an indexer.
- **Bridges registering themselves through their own extension or compiler pass**: not
  chosen because bridges are plain libraries without Symfony integration code.

## References

- `src/AiBundle.php`: `configure()`, `loadExtension()` and the `process*Config()` methods.
- `config/options.php`: the configuration tree and the per-bridge `$import()` calls.
- `config/platform/openai.php`, `config/store/memory.php`: examples of bridge fragments.
- `composer.json`: `symfony/ai-platform` as the only AI requirement, bridges in `require-dev`.
