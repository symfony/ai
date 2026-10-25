# Architecture Decision Records

Architecture Decision Records of the Platform component; the process and template live in
the repository's root `adr/README.md`.

| ADR | Decision |
| --- | --- |
| [0001](0001-a-platform-routes-each-invocation-to-one-of-several-providers.md) | **A platform routes each invocation to one of several providers.** `Platform` holds providers and asks a `ModelRouterInterface` which one serves each call. |
| [0002](0002-a-provider-composes-model-clients-result-converters-and-a-contract.md) | **A provider composes model clients, result converters and a contract.** Bridges plug in as parts of the shared `Provider` pipeline, which returns a lazily converted `DeferredResult`. |
| [0003](0003-models-are-resolved-by-name-from-catalogs-and-described-by-capabilities.md) | **Models are resolved by name from catalogs and described by capabilities.** A model catalog maps a name to a `Model` subclass with a list of `Capability` cases. |
| [0004](0004-request-payloads-are-built-by-model-aware-serializer-normalizers.md) | **Request payloads are built by model-aware Serializer normalizers.** `Contract` normalizes provider-neutral input, and bridges override only the normalizers that differ. |
| [0005](0005-the-invocation-lifecycle-is-extended-through-events.md) | **The invocation lifecycle is extended through events.** Routing, invocation and result events let listeners rewrite or observe each call without changing the core. |
