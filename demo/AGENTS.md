# AGENTS.md

AI agent guidance for the Symfony AI demo application.

## Project Overview

Symfony 7.3 demo showcasing AI integration with RAG, streaming chat, multimodal interactions, and MCP server functionality.

## Architecture

### Core Features
- **Chat Systems**: Blog, YouTube, Wikipedia, Audio, Stream implementations
- **Twig LiveComponents**: Real-time chat interfaces with Symfony UX
- **AI Agents**: Multiple configured agents with different models and tools
- **Vector Store**: ChromaDB integration for similarity search
- **MCP Tools**: Model Context Protocol for extending agent capabilities

### Technologies
- Symfony 7.3 + UX (LiveComponent, Turbo, Typed)
- OpenAI GPT-4o-mini + embeddings
- ChromaDB vector database
- FrankenPHP runtime

## Essential Commands

### Setup
```bash
# Start services
docker compose up -d
composer install
echo "OPENAI_API_KEY='sk-...'" > .env.local

# Initialize vector store
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
- **Agents**: blog, stream, youtube, wikipedia, audio
- **Platform**: OpenAI integration
- **Store**: ChromaDB vector store
- **Indexer**: Text embedding model

### Chat Pattern
- `Chat` class: Message flow and session management
- `TwigComponent` class: LiveComponent UI
- Agent configuration in `ai.yaml`
- Session storage with component keys

## Development Notes

- PHP 8.4+ with strict typing
- OpenAI GPT-4o-mini default model
- ChromaDB on port 8080
- LiveComponents for real-time UI
- Symfony DI and best practices