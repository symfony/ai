---
status: accepted
date: 2026-10-25
---

# 0001. A platform routes each invocation to one of several providers

## Context and Problem Statement

Applications talk to many inference backends (OpenAI, Anthropic, Ollama, ...) and want
one call site for all of them, sometimes with several backends behind it at once. The
caller should name a model, not wire up a backend, and the choice of backend should be
replaceable without changing that call site.

## Decision

`PlatformInterface` is the single user-facing entry point: `invoke(string|Model $model,
array|string|object $input, array $options = []): DeferredResult` plus
`getModelCatalog()`. The final `Platform` holds a non-empty list of `ProviderInterface`
instances (one per backend) and delegates the choice to a `ModelRouterInterface`.

- `ModelRouterInterface::resolve()` receives the model, the providers, the input and the
  options and returns a `ModelRouter\RoutingDecision` naming the provider and,
  optionally, a replacement model and replacement options.
- The default `ModelRouter\CatalogBasedModelRouter` picks the first provider whose
  `supports()` accepts the model, and throws `ModelNotFoundException` otherwise.
- Before routing, `Platform` dispatches a `Event\ModelRoutingEvent`; a listener may
  rewrite model, input and options, or set a provider directly, which bypasses the router.
- `Platform::getModelCatalog()` exposes a `ModelCatalog\CompositeModelCatalog` over the
  catalogs of all providers.

## Consequences

- **Good:** callers write `$platform->invoke('gpt-4o-mini', $messages)` regardless of how
  many backends are configured; routing by cost, rules or input is a router
  implementation, not a change to `Platform`.
- **Good:** `Platform` stays small and final; decorators such as `TraceablePlatform` wrap
  the interface rather than extend the class.
- **Trade-off:** with the default router, provider order matters: the first provider that
  supports a model name wins.
- **Trade-off:** a base `Model` instance has no model client in any provider and cannot be
  routed; callers must pass a bridge-specific subclass (the router says so in its error).

## Alternatives Considered

- **One platform object per backend only**: not chosen because applications using several
  backends would have to pick the backend themselves at every call site.
- **Routing inside each provider**: not chosen because the choice between providers needs
  to see all of them, which only the platform does.

## References

- `src/PlatformInterface.php`: the user-facing contract.
- `src/Platform.php`: dispatches `ModelRoutingEvent`, consults the router, delegates.
- `src/ModelRouterInterface.php`, `src/ModelRouter/RoutingDecision.php`: the routing contract.
- `src/ModelRouter/CatalogBasedModelRouter.php`: the default first-match router.
- `src/TraceablePlatform.php`: a decorator of `PlatformInterface`.
