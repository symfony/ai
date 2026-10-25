---
status: accepted
date: 2026-10-25
---

# 0001. A platform routes each invocation to one of several providers

## Context and Problem Statement

Applications talk to many inference backends (OpenAI, Anthropic, Ollama, ...) and want
one call site for all of them, sometimes with several backends at once. The caller should
name a model, not wire up a backend, and the backend should be replaceable without
changing that call site.

Until April 2026 one `Platform` instance meant one backend. An application using OpenAI
and Anthropic had to manage two platforms, and routing or model-level failover had no
natural place to live (#1923).

## Decision

`PlatformInterface` is the single user-facing entry point: `invoke(string|Model $model,
array|string|object $input, array $options = []): DeferredResult` plus
`getModelCatalog()`. The final `Platform` holds a non-empty list of `ProviderInterface`
instances (one per backend) and lets a `ModelRouterInterface` choose between them.

- `ModelRouterInterface::resolve()` receives the model, the providers, the input and the
  options. It returns a `ModelRouter\RoutingDecision` that names the provider and may
  also replace the model and the options.
- The default `ModelRouter\CatalogBasedModelRouter` picks the first provider whose
  `supports()` accepts the model and throws `ModelNotFoundException` otherwise. It never
  replaces the model, so routers that pick a model can delegate the provider lookup to it.
- Before routing, `Platform` dispatches an `Event\ModelRoutingEvent`. A listener may
  rewrite model, input and options, or set a provider directly, which skips the router.
- Model names and `Model` instances take the same path through the event and the router.
- `Platform::getModelCatalog()` returns a `ModelCatalog\CompositeModelCatalog` over the
  catalogs of all providers.

## Consequences

- **Good:** callers write `$platform->invoke('gpt-4o-mini', $messages)` however many
  backends are configured. Routing by cost, rules or input is a new router, not a change
  to `Platform`.
- **Good:** `Platform` stays small and final. Decorators such as `TraceablePlatform` and
  the Failover bridge's `FailoverPlatform` wrap the interface instead of extending the class.
- **Trade-off:** with the default router, provider order matters: the first provider that
  supports a model wins.
- **Trade-off:** routing happens before the call, so a router cannot react to runtime
  failures; failover belongs in a decorator.
- **Trade-off:** a base `Model` instance matches no model client and cannot be routed;
  callers must pass a bridge subclass (the error message says so).

## Alternatives Considered

- **One platform object per backend**: the design before #1923. Replaced because
  applications with several backends had to pick the backend at every call site.
- **A router that returns only a provider**: the first version of `ModelRouterInterface`
  (#1923). Widened to `RoutingDecision` in #2317 so routing can also choose the model,
  for every consumer of the platform and not only the Agent.
- **Routing inside each provider**: not chosen because choosing between providers needs
  to see all of them, which only the platform does.

## History

- 2024-11-23, php-llm/llm-chain#140 by Christopher Hertel: first decoupled platform layer; one `Platform` per backend, built by a bridge `PlatformFactory`.
- 2024-11-23, php-llm/llm-chain#149 by Christopher Hertel: added `PlatformInterface`, so chains and embedders depend on a contract.
- 2025-09-28, #640 by @OskarStark (Oskar Stark): `invoke()` takes a model name string; `getModelCatalog()` added to the interface.
- 2026-04-20, #1923 by @chr-hertel (Christopher Hertel): `Platform` became a router over providers, with `ModelRouterInterface`, `CatalogBasedModelRouter`, `CompositeModelCatalog` and `ModelRoutingEvent`, to end the one-platform-one-backend coupling.
- 2026-06-11, #2139 by @chr-hertel (Christopher Hertel): `invoke()` also accepts a `Model` instance, so a model missing from a shipped catalog can be used directly.
- 2026-07-21, #2317 by @chr-hertel (Christopher Hertel): the router returns a `RoutingDecision` and may pick the model and options; `Model` instances now go through the event and the router too.

## References

- `src/PlatformInterface.php`: the user-facing contract.
- `src/Platform.php`: dispatches `ModelRoutingEvent`, consults the router, delegates.
- `src/ModelRouterInterface.php`, `src/ModelRouter/RoutingDecision.php`: the routing contract.
- `src/ModelRouter/CatalogBasedModelRouter.php`: the default first-match router.
- `src/TraceablePlatform.php`: a decorator of `PlatformInterface`.
