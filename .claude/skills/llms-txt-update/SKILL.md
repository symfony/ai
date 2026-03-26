---
name: llms-txt-update
description: "Updates the llms.txt file at ai.symfony.com/public/llms.txt by scanning the Symfony AI monorepo for current components, bridges, documentation, and integrations. Use this skill whenever the user asks to update, regenerate, or refresh the llms.txt file, or mentions keeping llms.txt in sync with the codebase. Also trigger when the user says things like 'update the LLM file', 'refresh ai.symfony.com content for LLMs', or 'sync llms.txt'."
---

# llms.txt Updater for Symfony AI

This skill regenerates `ai.symfony.com/public/llms.txt` by scanning the Symfony AI monorepo. The llms.txt file follows the llms.txt convention — a markdown file that helps LLMs understand a project quickly.

## Why this matters

The Symfony AI project evolves rapidly: new platform bridges get added, new store backends appear, components gain features, and documentation grows. The llms.txt file needs to reflect the current state so that LLMs consuming it give accurate answers about the project.

## What to scan

Gather current data from these sources in the monorepo (root: the repo root, which is the parent of `ai.symfony.com/`):

1. **Homepage** — read `ai.symfony.com/templates/homepage.html.twig` and its included partials (`hero/_slide_*.html.twig`, `sections/_*.html.twig`) to extract the high-level project narrative, feature list, demo scenarios, and support links
2. **Components** — list directories in `src/` and read each `src/<component>/README.md` for the current description and install command
3. **Platform bridges** — list directories in `src/platform/src/Bridge/` to get the full set of supported AI platforms
4. **Store bridges** — list directories in `src/store/src/Bridge/` to get the full set of supported vector stores
5. **Chat bridges** — list directories in `src/chat/src/Bridge/` for message store backends
6. **Agent bridges** — list directories in `src/agent/src/Bridge/` for built-in tools (like Wikipedia, YouTube, SimilaritySearch)
7. **Documentation pages** — scan `docs/` for `.rst` files to build the documentation links section
8. **Cookbook entries** — scan `docs/cookbook/` for recipe pages

## Output structure

The llms.txt file follows this exact structure. Preserve the section order and formatting:

```
# Symfony AI

> [one-paragraph project description]

## Homepage (ai.symfony.com)
[High-level summary of the homepage content, derived from the Twig templates]

### Component Architecture
[Description of the layered architecture from _architecture.html.twig]

### Features
[Bullet list of all feature tabs from _features.html.twig: Model Inference, Streaming, Speech, Multi-Modal, Structured Output, Agents, Token Usage, Tool Calling, Subagents, RAG — with one-line descriptions]

### Third Party Integration Bridges
[Summary of bridge categories from _third-party.html.twig: Models, Platforms, Stores, Built-in Tools]

### Demos
[Demo app install command and list of demo scenarios from _demos.html.twig]

### Model Context Protocol (MCP)
[Summary from _mcp.html.twig]

### Symfony Mate
[Summary from _mate.html.twig]

### Get Involved & Get Support
[Links to docs, GitHub issues, and Slack channel from the CTA section in homepage.html.twig]

## Documentation
- [link]: description for each docs page

## Cookbook
- [link]: description for each cookbook entry

## Code
- [GitHub Repository](https://github.com/symfony/ai): Source code and issue tracker
- [Examples](https://github.com/symfony/ai/tree/main/examples): standalone examples
- [Demo Application](https://github.com/symfony/ai/tree/main/demo): full Symfony web app

## Components

### [Component Name]
[Description from README, install command, key features]

(repeat for each component)

## Supported AI Platforms
[comma-separated list derived from platform bridge directory names]

## Supported Vector Stores
[comma-separated list derived from store bridge directory names]
```

## How to map bridge directory names to display names

Bridge directories use PascalCase class naming. Map them to human-readable names:

**Platform bridges:**
- `OpenAi` → OpenAI
- `VertexAi` → Google VertexAI
- `AiMlApi` → AiMlApi
- `AmazeeAi` → AmazeeAi
- `HuggingFace` → HuggingFace
- `LmStudio` → LM Studio
- `DockerModelRunner` → Docker Model Runner
- `TransformersPhp` → TransformersPHP
- `OpenResponses` → OpenResponses
- `ModelsDev` → ModelsDev
- For others, split on capital letters (e.g., `DeepSeek` → DeepSeek, `ElevenLabs` → ElevenLabs)
- Skip `Cache`, `Failover`, and `Generic` — these are infrastructure bridges, not AI platforms

**Store bridges:**
- `AzureSearch` → Azure AI Search
- `ChromaDb` → ChromaDB
- `MariaDb` → MariaDB
- `MongoDb` → MongoDB Atlas
- `Postgres` → PostgreSQL (pgvector)
- `S3Vectors` → S3 Vectors
- `SurrealDb` → SurrealDB
- `ManticoreSearch` → ManticoreSearch
- `Cache` → PSR-6 Cache
- `Sqlite` → SQLite
- `Vektor` → Vektor
- For others, use the directory name as-is (e.g., `Pinecone`, `Redis`, `Meilisearch`)

## How to map docs filenames to URLs

Documentation is hosted at `https://symfony.com/doc/current/ai/`. Map `.rst` filenames:

- `docs/components/platform.rst` → `https://symfony.com/doc/current/ai/components/platform.html`
- `docs/bundles/ai-bundle.rst` → `https://symfony.com/doc/current/ai/bundles/ai-bundle.html`
- `docs/cookbook/rag-implementation.rst` → `https://symfony.com/doc/current/ai/cookbook/rag-implementation.html`
- Nested docs like `docs/components/mate/creating-extensions.rst` → check if they have their own page or are part of the parent

For link descriptions, read the first few lines of each `.rst` file to extract the title (the line above or below the `===` or `---` underline).

## Process

1. Read the current `ai.symfony.com/public/llms.txt`
2. Scan all sources listed above in parallel where possible
3. Compare what changed (new bridges, removed bridges, new docs pages, updated descriptions)
4. Regenerate the file, preserving the structure
5. Show the user a summary of what changed (e.g., "Added 2 new platform bridges: X, Y. Added cookbook entry: Z.")

## Important notes

- The file lives at `ai.symfony.com/public/llms.txt` relative to the repo root
- Keep descriptions concise — this file is meant to be consumed in a single LLM context window
- Component descriptions should include the composer install command
- Do not invent features or descriptions — derive everything from the actual README content and docs
- End the file with a newline
