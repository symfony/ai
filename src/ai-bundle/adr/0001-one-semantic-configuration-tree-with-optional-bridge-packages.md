---
status: accepted
date: 2026-10-25
---

# 0001. One semantic configuration tree with optional bridge packages

## Context and Problem Statement

The bundle connects the Platform, Agent, Store and Chat components and dozens of bridges to
Symfony. Each bridge is its own Composer package, and an application installs only the few
it needs. The bundle needs one way to configure all of them that still works when most
bridges are missing.

## Decision

All configuration lives under the `ai` key. `config/options.php` defines the main sections
(`platform`, `agent`, `store`, `message_store`, `chat`, `indexer`, `retriever`, and so on)
and adds one small file per bridge, for example `config/platform/openai.php`.

`AiBundle::loadExtension()` reads the configuration and creates the services in
`processPlatformConfig()`, `processStoreConfig()` and the other `process*Config()` methods.
Each bridge service is created by the bridge's own factory, is lazy, and gets a tag such as
`ai.platform` or `ai.store`.

Only `symfony/ai-platform` is required. Before it wires a bridge or component, the bundle
checks that the package is installed with `ContainerBuilder::willBeAvailable()`. If it is
missing, the bundle throws an error that names the `composer require` command. Services of
components that are not installed are removed.

## Consequences

- **Good:** one validated YAML tree for everything, and installing the bundle installs no
  bridge.
- **Good:** a missing package fails early, when the container is built, with a clear message.
- **Trade-off:** every new bridge adds a config file and code to `AiBundle.php`, so that
  class is large.
- **Trade-off:** bridge options appear twice, in the factory and in the config file, and must
  be kept in sync.

## Alternatives Considered

- **One bundle per bridge**: not chosen, because there would be no single place where an
  agent can name its platform or an indexer its store.

## References

- `src/AiBundle.php`: `loadExtension()` and the `process*Config()` methods.
- `config/options.php`: the configuration tree.
- `config/platform/openai.php`, `config/store/memory.php`: example bridge config files.
- `composer.json`: `symfony/ai-platform` as the only required AI package.
