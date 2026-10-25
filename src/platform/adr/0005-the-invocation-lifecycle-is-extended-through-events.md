---
status: accepted
date: 2026-10-25
---

# 0005. The invocation lifecycle is extended through events

## Context and Problem Statement

Cross-cutting behavior such as rendering message templates, turning a plain string into
a message bag, tracing, caching, failover or token accounting must apply to every
backend. Building each of these into `Platform` or `Provider` would grow them without
end, and bridges should not have to know about any of it.

## Decision

`Platform` and `Provider` accept an optional `Symfony\Contracts\EventDispatcher\EventDispatcherInterface`
and dispatch mutable events at fixed points of an invocation:

- `Event\ModelRoutingEvent` in `Platform::invoke()`, before routing: model, input and
  options can be rewritten, or a provider chosen.
- `Event\InvocationEvent` in `Provider::invoke()`, after model resolution and before the
  payload is built: model, input and options can be rewritten.
- `Event\ResultEvent` after the request is sent: the `DeferredResult` can be replaced.
- `Event\ResultConvertedEvent` once the result is converted, through
  `DeferredResult::onConvert()`: the converted result can be replaced; it names the
  provider.
- `Event\ResultErrorEvent` when conversion fails, through `DeferredResult::onError()`.

The component ships listeners for its own features on these events:
`EventListener\TemplateRendererListener` renders `Message\Template` content (consuming
the `template_vars` option) and `EventListener\StringToMessageBagListener` wraps a string
input for models with `Capability::INPUT_MESSAGES`.

## Consequences

- **Good:** features are opt-in listeners, and the core pipeline stays the same with or
  without them; without a dispatcher nothing is dispatched.
- **Good:** the result events follow lazy conversion, so listeners see the converted
  result without forcing conversion early.
- **Trade-off:** behavior such as template rendering depends on the listener being
  registered; without it, `Template` content is not rendered.
- **Trade-off:** a `ResultEvent` listener that resolves the result eagerly converts it
  before the conversion callbacks are registered and misses `ResultConvertedEvent`.

## Alternatives Considered

- **Decorating `PlatformInterface` for every concern**: still possible (`TraceablePlatform`
  does so), but not the general mechanism, because decorators cannot reach the resolved
  model or the step between resolution and payload building.
- **Middleware built into `Provider`**: not chosen because Symfony applications already
  configure listeners through the event dispatcher.

## References

- `src/Platform.php`, `src/Provider.php`: where each event is dispatched.
- `src/Event/`: the five event classes.
- `src/Result/DeferredResult.php`: `onConvert()` and `onError()` hooks.
- `src/EventListener/TemplateRendererListener.php`, `src/EventListener/StringToMessageBagListener.php`: bundled listeners.
