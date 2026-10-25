---
status: accepted
date: 2026-10-25
---

# 0003. Models are resolved by name from catalogs and described by capabilities

## Context and Problem Statement

Callers name models the way providers do, such as `claude-sonnet-4-0`. The platform still
needs to know which class handles a model and what the model can do (stream, call tools,
accept images). That knowledge belongs to the bridge, the application must be able to
extend it, and some backends (local runtimes, gateways) serve models nobody knows in
advance.

## Decision

A `Model` is a small value object: a non-empty name, a list of `Capability` cases and
default options. Bridges subclass it (for example `Bridge\Anthropic\Claude`) so model
clients, converters and normalizers can select by class, and a subclass may set default
options in its constructor.

A `ModelCatalog\ModelCatalogInterface` (`getModel()`, `getModels()`) turns names into
models. Bridges extend `ModelCatalog\AbstractModelCatalog`, which maps a name to
`{class, capabilities}` and throws `ModelNotFoundException` for unknown names. The name
may carry options as a query string (`model?temperature=0.5`), parsed by
`ModelOptionsParser`, and a size variant like `model:23b` falls back to its base entry.
A caller may also pass a ready `Model` instance to `invoke()`, which skips the catalog.

`Capability` is a string-backed enum grouped into input, output, functionality, voice,
image, video, embeddings and other cases; `Model::supports()` checks one. Catalogs
compose: `CompositeModelCatalog` merges several, `CachedModelCatalog` caches an API-backed
one, and `FallbackModelCatalog` accepts any name with all capabilities.

## Consequences

- **Good:** routing (ADR 0001) and listeners such as `StringToMessageBagListener` decide
  on capabilities and catalogs, not on hard-coded model lists.
- **Good:** applications add models through a catalog's additional models (also in the
  AI Bundle config), without a new bridge release.
- **Trade-off:** capabilities are declared, not discovered; a wrong catalog entry shows up
  only as a provider error.
- **Trade-off:** shipped catalogs go stale as providers release models; the ways out are
  additional models, `FallbackModelCatalog`, an API-backed catalog or a `Model` instance.
- **Trade-off:** `FallbackModelCatalog` claims every capability, so capability checks mean
  nothing for models it resolves.

## Alternatives Considered

- **Marker interfaces such as `LanguageModel` and `EmbeddingsModel`**: the llm-chain
  design, replaced in php-llm/llm-chain#326 by one `Model` class with capabilities.
- **Model name constants on each model class, capabilities computed in its constructor**:
  replaced in #640 by catalogs, so models are plain strings, defined in one place per
  bridge, and custom models need no subclass.
- **Asking each provider's API for model metadata on every call**: not the default; it is
  possible with a custom catalog wrapped in `CachedModelCatalog`.

## History

- 2025-06-01, php-llm/llm-chain#326 by Christopher Hertel: one global `Model` class with an explicit `Capability` list replaced the model-type interfaces.
- 2025-07-18, #153 by @welcoMattic (Mathieu Santostefano): `Capability` became an enum instead of a class with constants.
- 2025-09-28, #640 by @OskarStark (Oskar Stark): added `ModelCatalogInterface` and `AbstractModelCatalog`, removed the model constants, and let applications add models.
- 2025-10-01, #712 by @OskarStark (Oskar Stark): size variants such as `model:23b` resolve to their base entry.
- 2025-10-11, #777 by @OskarStark (Oskar Stark): renamed `DynamicModelCatalog` to `FallbackModelCatalog` to say what it is for.
- 2026-10-23, #2743 by @chr-hertel (Christopher Hertel): added `CachedModelCatalog` for API-backed catalogs, made `CompositeModelCatalog` a public tool, and gave a bare `Model` a helpful error.

## References

- `src/Model.php`, `src/Capability.php`: the model value object and capability enum.
- `src/ModelCatalog/ModelCatalogInterface.php`, `src/ModelCatalog/AbstractModelCatalog.php`: name resolution.
- `src/ModelCatalog/CompositeModelCatalog.php`, `src/ModelCatalog/CachedModelCatalog.php`, `src/ModelCatalog/FallbackModelCatalog.php`: composition.
- `src/ModelOptionsParser.php`: options in the model name.
- `src/Bridge/Anthropic/Claude.php`, `src/Bridge/Anthropic/ModelCatalog.php`: a bridge's model and catalog.
