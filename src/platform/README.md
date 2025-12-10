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

| Platform | Package |
|----------|---------|
| AI.ML API | `symfony/ai-ai-ml-api-platform` |
| Albert | `symfony/ai-albert-platform` |
| Anthropic | `symfony/ai-anthropic-platform` |
| Azure OpenAI | `symfony/ai-azure-platform` |
| AWS Bedrock | `symfony/ai-bedrock-platform` |
| Cartesia | `symfony/ai-cartesia-platform` |
| Cerebras | `symfony/ai-cerebras-platform` |
| Decart | `symfony/ai-decart-platform` |
| DeepSeek | `symfony/ai-deep-seek-platform` |
| Docker Model Runner | `symfony/ai-docker-model-runner-platform` |
| ElevenLabs | `symfony/ai-eleven-labs-platform` |
| Generic | `symfony/ai-generic-platform` |
| Google Gemini | `symfony/ai-gemini-platform` |
| Hugging Face | `symfony/ai-hugging-face-platform` |
| LM Studio | `symfony/ai-lm-studio-platform` |
| Meta Llama | `symfony/ai-meta-platform` |
| Mistral | `symfony/ai-mistral-platform` |
| Ollama | `symfony/ai-ollama-platform` |
| OpenAI | `symfony/ai-open-ai-platform` |
| OpenRouter | `symfony/ai-open-router-platform` |
| Perplexity | `symfony/ai-perplexity-platform` |
| Replicate | `symfony/ai-replicate-platform` |
| Scaleway | `symfony/ai-scaleway-platform` |
| TransformersPHP | `symfony/ai-transformers-php-platform` |
| Google Vertex AI | `symfony/ai-vertex-ai-platform` |
| Voyage | `symfony/ai-voyage-platform` |

**This repository is a READ-ONLY sub-tree split**. See
https://github.com/symfony/ai to create issues or submit pull requests.

## Resources

- [Documentation](https://symfony.com/doc/current/ai/components/platform.html)
- [Report issues](https://github.com/symfony/ai/issues) and
  [send Pull Requests](https://github.com/symfony/ai/pulls)
  in the [main Symfony AI repository](https://github.com/symfony/ai)
