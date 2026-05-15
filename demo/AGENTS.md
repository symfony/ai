# AGENTS.md

AI agent guidance for the Symfony AI demo application.

## Project Overview

Symfony 8.0 demo showcasing AI integration with RAG, streaming chat, multi-agent
orchestration, voice/speech, image cropping, webcam video captioning, YouTube and
Wikipedia Q&A, and MCP server functionality.

## Architecture

### Core Features
- **Chat Systems**: Blog, Stream, YouTube, Recipe, Wikipedia, Speech implementations
- **Twig LiveComponents**: Real-time chat interfaces with Symfony UX
- **AI Agents**: Multiple configured agents with different models and tools
- **Multi-Agent Orchestration**: `support` orchestrator hands off to `technical`/`fallback`
- **Vector Store**: PostgreSQL (pgvector) integration for similarity search
- **MCP Tools**: Model Context Protocol for extending agent capabilities

### Technologies
- Symfony 8.0 + UX (LiveComponent, Turbo, Typed)
- OpenAI (`gpt-4.1`, `gpt-5-mini`) + `text-embedding-ada-002` embeddings
- PostgreSQL with pgvector vector database

## Essential Commands

### Setup
```bash
# Start services
docker compose up -d
composer install
echo "OPENAI_API_KEY='sk-...'" > .env.local

# Initialize vector store (setup must run before indexing)
symfony console ai:store:setup ai.store.postgres.symfony_blog
symfony console ai:store:index blog -vv
symfony console ai:store:retrieve blog "Week of Symfony"

# Start server
symfony serve -d
```

### Testing
```bash
vendor/bin/phpunit
vendor/bin/phpunit tests/SmokeTest.php
```

### Code Quality
```bash
vendor/bin/php-cs-fixer fix
vendor/bin/phpstan analyse
```

### MCP Server
```bash
symfony console mcp:server
# Test: {"method":"tools/list","jsonrpc":"2.0","id":1}
```

## Configuration

### AI Setup (`config/packages/ai.yaml`)
- **Agents**: blog, stream, youtube, recipe, wikipedia, speech, plus the `support`
  multi-agent (orchestrator, technical, fallback)
- **Platform**: OpenAI integration (`huggingface` is configured but unused)
- **Store**: PostgreSQL (pgvector) vector store
- **Indexer**: Text embedding model (`text-embedding-ada-002`)

### Chat Pattern
- `Chat` class: Message flow and session management
- `TwigComponent` class: LiveComponent UI
- Agent configuration in `ai.yaml`
- Session storage with component keys

## Development Notes

- PHP 8.4+ with strict typing
- OpenAI `gpt-4.1` (blog, stream) and `gpt-5-mini` (everything else) models
- PostgreSQL (pgvector) for the vector store
- LiveComponents for real-time UI
- Symfony DI and best practices

<!-- BEGIN AI_MATE_INSTRUCTIONS -->
AI Mate Summary:
- Role: MCP-powered, project-aware coding guidance and tools.
- Required action: Read and follow `mate/AGENT_INSTRUCTIONS.md` before taking any action in this project, and prefer MCP tools over raw CLI commands whenever possible.
- Installed extensions: symfony/ai-mate, symfony/ai-monolog-mate-extension, symfony/ai-symfony-mate-extension.
<!-- END AI_MATE_INSTRUCTIONS -->
