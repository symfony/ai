# Symfony AI - Platform Component

The Platform component provides an abstraction for interacting with different models, their providers and contracts.

**This Component is experimental**.
[Experimental features](https://symfony.com/doc/current/contributing/code/experimental.html)
are not covered by Symfony's
[Backward Compatibility Promise](https://symfony.com/doc/current/contributing/code/bc.html).

## Installation

```bash
composer require symfony/ai-platform
```

## Platform Bridges

To use a specific AI platform, install the corresponding bridge package:

| Platform            | Package                                   |
|---------------------|-------------------------------------------|
| AI.ML API           | `symfony/ai-ai-ml-api-platform`           |
| Albert              | `symfony/ai-albert-platform`              |
| amazee.ai           | `symfony/ai-amazee-ai-platform`           |
| Anthropic           | `symfony/ai-anthropic-platform`           |
| Azure OpenAI        | `symfony/ai-azure-platform`               |
| AWS Bedrock         | `symfony/ai-bedrock-platform`             |
| Cache               | `symfony/ai-cache-platform`               |
| Cartesia            | `symfony/ai-cartesia-platform`            |
| Cerebras            | `symfony/ai-cerebras-platform`            |
| Claude Code         | `symfony/ai-claude-code-platform`         |
| Codex               | `symfony/ai-codex-platform`               |
| Cohere              | `symfony/ai-cohere-platform`              |
| Decart              | `symfony/ai-decart-platform`              |
| DeepSeek            | `symfony/ai-deep-seek-platform`           |
| Docker Model Runner | `symfony/ai-docker-model-runner-platform` |
| ElevenLabs          | `symfony/ai-eleven-labs-platform`         |
| Failover            | `symfony/ai-failover-platform`            |
| Generic             | `symfony/ai-generic-platform`             |
| Google Gemini       | `symfony/ai-gemini-platform`              |
| Hugging Face        | `symfony/ai-hugging-face-platform`        |
| LM Studio           | `symfony/ai-lm-studio-platform`           |
| Meta Llama          | `symfony/ai-meta-platform`                |
| Mistral             | `symfony/ai-mistral-platform`             |
| Models.dev          | `symfony/ai-models-dev-platform`          |
| Ollama              | `symfony/ai-ollama-platform`              |
| OpenAI              | `symfony/ai-open-ai-platform`             |
| Open Responses      | `symfony/ai-open-responses-platform`      |
| OpenRouter          | `symfony/ai-open-router-platform`         |
| OVH                 | `symfony/ai-ovh-platform`                 |
| Perplexity          | `symfony/ai-perplexity-platform`          |
| Replicate           | `symfony/ai-replicate-platform`           |
| Scaleway            | `symfony/ai-scaleway-platform`            |
| TransformersPHP     | `symfony/ai-transformers-php-platform`    |
| Google Vertex AI    | `symfony/ai-vertex-ai-platform`           |
| Voyage              | `symfony/ai-voyage-platform`              |

**This repository is a READ-ONLY sub-tree split**. See
https://github.com/symfony/ai to create issues or submit pull requests.

## Exception strategy

The Platform component defines its own exception taxonomy. Consumers of this component should depend on Platform exceptions rather than on exceptions from lower-level libraries such as HTTP clients, process execution libraries, or provider SDKs.

In other words, the public contract of this component is expressed through exceptions in the `Symfony\AI\Platform\Exception\` namespace. Bridge implementations are expected to normalize provider-specific and transport-specific failures into these Platform exceptions before they cross the `PlatformInterface`, `ModelClientInterface`, or `ResultConverterInterface` boundaries.

### Exception categories

The current exception set is intended to cover several different kinds of failures, including:

- authentication and authorization problems, for example `AuthenticationException`
- invalid input or request problems:
  - `InvalidArgumentException` for invalid usage of the Platform API by the caller
  - `InvalidRequestException` for semantically invalid requests in the Platform/provider contract
  - `BadRequestException` for generic provider-side request rejection when no narrower Platform exception applies
- model resolution and support problems, for example `ModelNotFoundException` or `MissingModelSupportException`
- provider-side runtime limits or policies, for example `RateLimitExceededException`, `ExceedContextSizeException`, or `ContentFilterException`
- conversion or contract mismatches, for example `UnexpectedResultTypeException`
- broader component-level failures, through `LogicException`, `RuntimeException`, and `ExceptionInterface`

### Recoverable and unrecoverable failures

When handling failures, consumers will often want to distinguish between:

- recoverable runtime failures, such as temporary transport errors, transient provider failures, or rate limiting, where retry, backoff, or failover may be appropriate
- unrecoverable failures, such as authentication errors, invalid requests, unsupported models, or invalid configuration, where the request is not expected to succeed without changing the input or setup

Concrete Platform exceptions should make that distinction clear enough for consumers to write reliable error-handling and retry logic.

### What consumers should catch

Code that depends on this component should prefer catching Platform exceptions at the appropriate level of specificity.

Typical strategies include:

- catching a specific exception, such as `RateLimitExceededException`, when a dedicated reaction is needed
- catching a broader Platform runtime exception when multiple provider/runtime failures should be handled the same way
- catching `ExceptionInterface` as a final fallback for component-level handling

Consumers should avoid coupling application code to bridge-specific exceptions or to exceptions from lower-level dependencies, because that reduces the value of the Platform abstraction and makes switching providers harder.

### What bridge authors should do

Implementers of Platform bridges should normalize lower-level failures into Platform exceptions as close as possible to where the failure is detected.

As a rule of thumb:

- `ModelClientInterface` implementations should normalize transport, provider, authentication, throttling, and model lookup/support failures
- `ResultConverterInterface` implementations should normalize response-shape, content filtering, context-size, and conversion failures
- `PlatformInterface` implementations should preserve the Platform-level exception contract and should not leak raw dependency exceptions across the abstraction boundary

## Resources

- [Documentation](https://symfony.com/doc/current/ai/components/platform.html)
- [Report issues](https://github.com/symfony/ai/issues) and
  [send Pull Requests](https://github.com/symfony/ai/pulls)
  in the [main Symfony AI repository](https://github.com/symfony/ai)
