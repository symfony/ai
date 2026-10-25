---
status: accepted
date: 2026-10-25
---

# 0002. A provider composes model clients, result converters and a contract

## Context and Problem Statement

Each backend differs in three places: how a request body looks, how it is sent, and how
the answer is read. Everything else (model lookup, events, result handling) is the same
for every backend. Bridges in `src/Bridge/` must plug in without rewriting the common
flow, and one backend may serve models with very different endpoints (chat, embeddings,
speech).

## Decision

The final `Provider` implements `ProviderInterface` and runs the same pipeline for every
backend. It is built from parts a bridge supplies:

- a name, a `ModelCatalog\ModelCatalogInterface` and a `Contract` (see ADR 0003 and 0004);
- an iterable of `ModelClientInterface`, each with `supports(Model)` and
  `request(Model, $payload, $options): RawResultInterface`, which makes the HTTP (or
  other) call;
- an iterable of `ResultConverterInterface`, each with `supports(Model)`,
  `convert(RawResultInterface, $options): ResultInterface` and an optional
  `getTokenUsageExtractor()`.

`Provider::invoke()` resolves the model, builds the payload through the contract, merges
the model's default options with the call options, asks the first supporting model client
for a raw result, and wraps it with the first supporting converter in a
`Result\DeferredResult`. Conversion happens only when the caller reads the result
(`asText()`, `asObject()`, `asStream()`, ...). A bridge ships a `Factory` with
`createProvider()` and `createPlatform()` that puts these parts together, and is
published as its own Composer package (`symfony/ai-<name>-platform`) that depends on
`symfony/ai-platform`.

## Consequences

- **Good:** a bridge holds only backend-specific code; a new endpoint is one more model
  client and converter, selected by model class.
- **Good:** lazy conversion lets the caller choose buffered or streamed reading, and keeps
  HTTP responses asynchronous until needed, so several calls can run in parallel.
- **Good:** an application installs only the bridges it uses.
- **Trade-off:** a model with no matching client throws `ModelNotFoundException`, one with
  no matching converter a `RuntimeException`; mismatches show up only when invoking.
- **Trade-off:** the first supporting client or converter wins, so `supports()` checks must
  not overlap within one provider.

## Alternatives Considered

- **One provider class per backend**: not chosen because event dispatching, option
  merging and result wrapping would be repeated in every bridge.
- **Eager conversion returning `ResultInterface`**: not chosen because streaming and
  parallel calls need control over when the raw response is read (php-llm/llm-chain#141).
- **A closure as the converter inside the deferred result**: used until #745, which
  passes the `ResultConverterInterface` explicitly instead.
- **All bridges inside `symfony/ai-platform`**: the layout until #1093, which split them
  into their own packages, like the store bridges.

## History

- 2024-11-23, php-llm/llm-chain#140 by Christopher Hertel: introduced `ModelClient` and `ResponseConverter` as the two bridge seams.
- 2024-11-23, php-llm/llm-chain#141 by Christopher Hertel: async responses, so calls can run in parallel and are read later.
- 2025-07-19, #154 by @chr-hertel (Christopher Hertel): renamed responses to results (`RawResultInterface`, `ResultConverterInterface`) and the call to `invoke()`.
- 2025-10-07, #745 by @chr-hertel (Christopher Hertel): renamed `ResultPromise` to `DeferredResult`, because it does not behave like a promise, and passed the converter explicitly.
- 2025-12-17, #1093 by @OskarStark (Oskar Stark): split the platform bridges into their own Composer packages.
- 2026-04-20, #1923 by @chr-hertel (Christopher Hertel): extracted `Provider` from `Platform` as one self-contained backend connection; bridges got a `Factory` with `createProvider()`.

## References

- `src/ProviderInterface.php`, `src/Provider.php`: the shared pipeline.
- `src/ModelClientInterface.php`, `src/ResultConverterInterface.php`: the bridge seams.
- `src/Result/DeferredResult.php`, `src/Result/TypedResultTrait.php`: lazy, typed access.
- `src/Bridge/Anthropic/Factory.php`, `src/Bridge/Anthropic/composer.json`: a bridge assembling a provider.
