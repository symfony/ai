---
status: accepted
date: 2026-10-25
---

# 0004. Request payloads are built by model-aware Serializer normalizers

## Context and Problem Statement

The same input (a `Message\MessageBag` with text, images and tool calls, a list of
`Tool\Tool` definitions) must become a different JSON body for every backend, and even for
different models of one backend. The message classes must stay provider-neutral, and a
bridge must be able to override only the parts that differ.

## Decision

The `Contract` class turns input into a payload with the Symfony Serializer.
`Contract::create(array $normalizers = [])` builds a `Serializer` from the given
normalizers followed by the defaults (message bag, system, user, assistant and tool-call
messages, text, image, image URL and audio content, tools, tool calls, and
`JsonSerializableNormalizer` as a generic extension point). Because the Serializer uses
the first normalizer that supports the data, normalizers passed in take precedence.

- `createRequestPayload()` normalizes the input with the `Model` and the invocation
  options in the context (`Contract::CONTEXT_MODEL`, `Contract::CONTEXT_OPTIONS`).
- `createToolOption()` normalizes the `tools` option the same way.
- Bridge normalizers extend `Contract\Normalizer\ModelContractNormalizer`, which declares
  the supported data class and a `supportsModel(Model)` check against the model in the
  context.
- A bridge ships its own contract, for example `Bridge\Anthropic\Contract\AnthropicContract`,
  which calls `parent::create()` with its normalizers; a caller may still pass a custom
  `Contract` to the bridge factory.

## Consequences

- **Good:** message and content classes have no provider knowledge; one bridge's format
  cannot leak into another's.
- **Good:** a bridge overrides only the normalizers whose format differs and inherits the
  rest, and applications can add normalizers without forking a bridge.
- **Trade-off:** precedence depends on array order, and a normalizer that forgets to check
  the model in its `supportsModel()` can capture data for models it does not serve.
- **Trade-off:** the component depends on `symfony/serializer` for what is mostly array
  building.

## Alternatives Considered

- **A `toArray()` method per message class**: not chosen because the format differs per
  backend, which would push provider knowledge into the neutral message classes.
- **Payload building inside each model client**: not chosen because normalizers compose
  and can be shared and overridden per content type.

## References

- `src/Contract.php`: default normalizers, `createRequestPayload()`, `createToolOption()`.
- `src/Contract/Normalizer/ModelContractNormalizer.php`: model-aware base normalizer.
- `src/Contract/Normalizer/Message/`: the default message normalizers.
- `src/Bridge/Anthropic/Contract/AnthropicContract.php`: a bridge contract.
- `src/Provider.php`: where the contract is applied during `invoke()`.
