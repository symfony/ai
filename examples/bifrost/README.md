# Bifrost Examples

[Bifrost](https://docs.getbifrost.ai/) is a self-hosted, OpenAI-compatible LLM
gateway that routes requests to many AI providers using the `provider/model`
notation (e.g. `openai/gpt-4o-mini`, `anthropic/claude-3-opus`,
`cohere/embed-english-v3`).

## Prerequisites

Start a local Bifrost instance, for example with NPX:

```bash
npx @getbifrost/cli@latest start
```

Then configure `BIFROST_ENDPOINT` (defaults to `http://localhost:8080`) and an
optional `BIFROST_API_KEY` in `examples/.env.local`.

## Examples

```bash
php bifrost/chat.php
php bifrost/stream.php
php bifrost/embeddings.php
php bifrost/text-to-speech.php | mpg123 -
php bifrost/speech-to-text.php
php bifrost/image-generation.php
```
