---
status: accepted
date: 2026-10-25
---

# 0002. A provider composes model clients, result converters and a contract

## Context and Problem Statement

Each backend differs in three places: how a request body looks, how it is sent, and how
the answer is read. Everything else (model lookup, events, result handling) is the same
for every backend. Bridges in `src/Bridge/` must be able to plug in without re-implementing
the common flow, and a single backend may serve models with very different endpoints
(chat, embeddings, speech).

## Decision

The final `Provider` implements `ProviderInterface` and runs the same pipeline for every
backend. It is built from parts a bridge supplies:

- a name, a `ModelCatalog\ModelCatalogInterface` and a `Contract` (see ADR 0003 and 0004);
- an iterable of `ModelClientInterface`, each with `supports(Model)` and
  `request(Model, $payload, $options): RawResultInterface`, which performs the HTTP (or
  other) call;
- an iterable of `ResultConverterInterface`, each with `supports(Model)`,
  `convert(RawResultInterface, $options): ResultInterface` and an optional
  `getTokenUsageExtractor()`.

`Provider::invoke()` resolves the model, builds the payload through the contract, merges
the model's default options with the call options, asks the first supporting model client
for a raw result, and wraps it with the first supporting converter in a
`Result\DeferredResult`. Conversion happens only when the caller reads the result
(`asText()`, `asObject()`, `asStream()`, ...). A bridge ships a `Factory` with
`createProvider()` and `createPlatform()` that assembles these parts, and is published as
its own Composer package (`symfony/ai-<name>-platform`) depending on `symfony/ai-platform`.

## Consequences

- **Good:** a bridge contains only backend-specific code; supporting a new endpoint is one
  more model client and converter selected by model class.
- **Good:** lazy conversion lets a caller choose between buffered and streamed reading and
  keeps HTTP responses asynchronous until needed.
- **Trade-off:** a model with no matching client throws `ModelNotFoundException`, with no
  matching converter a `RuntimeException`; mismatches surface only at invocation time.
- **Trade-off:** the first supporting client or converter wins, so `supports()` checks must
  not overlap within one provider.

## Alternatives Considered

- **One provider class per backend**: not chosen because the event dispatching, option
  merging and result wrapping would be duplicated in every bridge.
- **Eager conversion returning `ResultInterface`**: not chosen because streaming and
  asynchronous jobs need control over when the raw response is consumed.

## References

- `src/ProviderInterface.php`, `src/Provider.php`: the shared pipeline.
- `src/ModelClientInterface.php`, `src/ResultConverterInterface.php`: the bridge seams.
- `src/Result/DeferredResult.php`, `src/Result/TypedResultTrait.php`: lazy, typed access.
- `src/Bridge/Anthropic/Factory.php`, `src/Bridge/Anthropic/composer.json`: a bridge assembling a provider.
