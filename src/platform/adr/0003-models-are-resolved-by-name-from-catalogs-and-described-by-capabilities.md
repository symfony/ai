---
status: accepted
date: 2026-10-25
---

# 0003. Models are resolved by name from catalogs and described by capabilities

## Context and Problem Statement

Callers identify models by provider names such as `claude-sonnet-4-0`, but the platform
needs to know which class handles a model and what the model can do (stream, call tools,
accept images). That knowledge belongs to the bridge, must be extensible by the
application, and some backends (local runtimes, gateways) serve models that are not known
in advance.

## Decision

A `Model` is a small value object: a non-empty name, a list of `Capability` cases and
default options. Bridges subclass it (for example `Bridge\Anthropic\Claude`) so model
clients, converters and normalizers can select by class, and a subclass may set default
options in its constructor.

Names are turned into models by a `ModelCatalog\ModelCatalogInterface` (`getModel()`,
`getModels()`). Bridges extend `ModelCatalog\AbstractModelCatalog`, which maps a name to
`{class, capabilities}` and throws `ModelNotFoundException` for unknown names. The name
may carry options as a query string (`model?temperature=0.5`), parsed by
`ModelOptionsParser`, and a size variant like `model:23b` falls back to its base entry.

`Capability` is a string-backed enum grouped into input, output, functionality, voice,
image, video, embeddings and other cases; `Model::supports()` checks one. Further catalogs
compose: `CompositeModelCatalog` merges several, `CachedModelCatalog` caches an API-backed
one, and `FallbackModelCatalog` accepts any name with all capabilities.

## Consequences

- **Good:** routing (ADR 0001) and listeners such as `StringToMessageBagListener` decide
  on capabilities and catalogs instead of hard-coded model lists.
- **Good:** applications add models through a catalog's additional models, without a
  release of the bridge.
- **Trade-off:** capabilities are declared, not discovered; a wrong catalog entry
  surfaces only as a provider error.
- **Trade-off:** `FallbackModelCatalog` claims every capability, so capability checks are
  meaningless for models resolved through it.

## Alternatives Considered

- **Free-form model name strings everywhere**: not chosen because model clients and
  converters need a type to select on, and listeners need capabilities.
- **Querying each provider's API for model metadata on every call**: not chosen as the
  default; it is possible through a custom catalog, wrapped in `CachedModelCatalog`.

## References

- `src/Model.php`, `src/Capability.php`: the model value object and capability enum.
- `src/ModelCatalog/ModelCatalogInterface.php`, `src/ModelCatalog/AbstractModelCatalog.php`: name resolution.
- `src/ModelCatalog/CompositeModelCatalog.php`, `src/ModelCatalog/CachedModelCatalog.php`, `src/ModelCatalog/FallbackModelCatalog.php`: composition.
- `src/ModelOptionsParser.php`: options in the model name.
- `src/Bridge/Anthropic/Claude.php`, `src/Bridge/Anthropic/ModelCatalog.php`: a bridge's model and catalog.
