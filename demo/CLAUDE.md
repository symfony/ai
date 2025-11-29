# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Symfony 7.3 demo application showcasing AI integration capabilities using Symfony AI components. The application demonstrates various AI use cases including RAG (Retrieval Augmented Generation), streaming chat, multimodal interactions, and MCP (Model Context Protocol) server functionality.

## Architecture

### Core Components
- **Chat Systems**: Multiple specialized chat implementations in `src/` (Blog, YouTube, Wikipedia, Audio, Stream)
- **Twig LiveComponents**: Interactive UI components using Symfony UX for real-time chat interfaces  
- **AI Agents**: Configured agents with different models, tools, and system prompts
- **Vector Store**: ChromaDB integration for embedding storage and similarity search
- **MCP Tools**: Model Context Protocol tools for extending agent capabilities

### Key Technologies
- Symfony 7.3 with UX components (LiveComponent, Turbo, Typed)
- OpenAI GPT-4o-mini models and embeddings
- ChromaDB vector database
- FrankenPHP runtime
- Docker Compose for ChromaDB service

## Development Commands

### Environment Setup
```bash
# Start ChromaDB service
docker compose up -d

# Install dependencies
composer install

# Set OpenAI API key
echo "OPENAI_API_KEY='sk-...'" > .env.local

# Initialize vector store
symfony console ai:store:index blog -vv

# Test vector store
symfony console ai:store:retrieve blog "Week of Symfony"

# Start development server
symfony serve -d
```

### Testing
```bash
# Run all tests
vendor/bin/phpunit

# Run specific test
vendor/bin/phpunit tests/SmokeTest.php

# Run with coverage
vendor/bin/phpunit --coverage-text
```

### Code Quality
```bash
# Fix code style (uses PHP CS Fixer via Shim)
vendor/bin/php-cs-fixer fix

# Static analysis
vendor/bin/phpstan analyse
```

### MCP Server
```bash
# Start MCP server
symfony console mcp:server

# Test MCP server (paste in terminal)
{"method":"tools/list","jsonrpc":"2.0","id":1}
```

## Configuration Structure

### AI Configuration (`config/packages/ai.yaml`)
- **Agents**: Multiple pre-configured agents (blog, stream, youtube, wikipedia, audio)
- **Platform**: OpenAI integration with API key from environment
- **Store**: ChromaDB vector store for similarity search
- **Indexer**: Text embedding model configuration

### Chat Implementations
Each chat type follows the pattern:
- `Chat` class: Handles message flow and session management
- `TwigComponent` class: LiveComponent for UI interaction
- Agent configuration in `ai.yaml`

### Session Management
Chat history stored in Symfony sessions with component-specific keys (e.g., 'blog-chat', 'stream-chat').

## Development Notes

- Uses PHP 8.4+ with strict typing and modern PHP features
- All AI agents use OpenAI GPT-4o-mini by default
- Vector embeddings use OpenAI's text-ada-002 model
- ChromaDB runs on port 8080 (mapped from container port 8000)
- Application follows Symfony best practices with dependency injection
- LiveComponents provide real-time UI updates without custom JavaScript
- MCP server enables tool integration for AI agents