---
status: accepted
date: 2026-10-25
---

# 0004. Request payloads are built by model-aware Serializer normalizers

## Context and Problem Statement

The same input (a `Message\MessageBag` with text, images and tool calls, a list of
`Tool\Tool` definitions) must become a different JSON body for every backend, and even for
different models of one backend. The OpenAI format serves as the base, and other backends
and models differ from it in details. The message classes must stay provider-neutral, and a
bridge must be able to override only the parts that differ.

## Decision

The `Contract` class turns input into a payload with the Symfony Serializer.
`Contract::create(array $normalizers = [])` builds a `Serializer` from the given
normalizers followed by the defaults (message bag, system, user, assistant and tool-call
messages, text, image, image URL and audio content, tools, tool calls, and
`JsonSerializableNormalizer` as a generic fallback). The Serializer uses the first
normalizer that supports the data, so normalizers passed in win.

- `createRequestPayload()` normalizes the input with the `Model` and the invocation
  options in the context (`Contract::CONTEXT_MODEL`, `Contract::CONTEXT_OPTIONS`).
- `createToolOption()` normalizes the `tools` option the same way.
- Bridge normalizers extend `Contract\Normalizer\ModelContractNormalizer`, which declares
  the supported data class and a `supportsModel(Model)` check against the model in the
  context.
- A bridge ships its own contract, for example `Bridge\Anthropic\Contract\AnthropicContract`,
  which calls `parent::create()` with its normalizers. A caller may still pass a custom
  `Contract` to the bridge factory.

## Consequences

- **Good:** message and content classes know nothing about providers; one bridge's format
  cannot leak into another's.
- **Good:** a bridge overrides only the normalizers whose format differs, and applications
  can add normalizers without forking a bridge.
- **Good:** custom message or content classes can implement `JsonSerializable` instead of
  shipping a normalizer.
- **Trade-off:** precedence depends on array order, and a normalizer that skips the model
  check in `supportsModel()` can capture data for models it does not serve.
- **Trade-off:** the component depends on `symfony/serializer` for what is mostly array
  building.

## Alternatives Considered

- **`JsonSerializable` on the message classes**: the llm-chain design from the start.
  Dropped in php-llm/llm-chain#326 in favor of normalizers, which handle the differences
  between the OpenAI base format and other backends. It returned only as a fallback
  (php-llm/llm-chain#377).
- **A fixed set of normalizers inside the contract**: the first contract
  (php-llm/llm-chain#326). Opened in php-llm/llm-chain#372, because input messages are
  flexible while the normalizers were fixed internally.
- **Payload building inside each model client**: not chosen because normalizers compose
  and can be shared and overridden per content type.

## History

- 2023-11-04, 4804cf0f by Christopher Hertel: the first LlmChain commit; messages build their own JSON through `JsonSerializable`.
- 2025-06-01, php-llm/llm-chain#326 by Christopher Hertel: introduced `Contract` with Serializer normalizers and `ModelContractNormalizer` to handle the OpenAI base format versus model-specific differences.
- 2025-07-05, php-llm/llm-chain#372 by Denis Zunke: `Contract::create()` accepts custom normalizers, and bridges such as Anthropic and Google got their own contract sets.
- 2025-07-05, php-llm/llm-chain#377 by Denis Zunke: added `JsonSerializableNormalizer` as a fallback for custom message and content classes.

## References

- `src/Contract.php`: default normalizers, `createRequestPayload()`, `createToolOption()`.
- `src/Contract/Normalizer/ModelContractNormalizer.php`: model-aware base normalizer.
- `src/Contract/Normalizer/Message/`: the default message normalizers.
- `src/Bridge/Anthropic/Contract/AnthropicContract.php`: a bridge contract.
- `src/Provider.php`: where the contract is applied during `invoke()`.
