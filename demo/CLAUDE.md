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

## Available MCP Tools

**IMPORTANT**: This project includes the Symfony AI Mate MCP server with powerful debugging and inspection tools. **USE THESE TOOLS PROACTIVELY** whenever working with Symfony AI features, logs, or system information.

### Symfony AI Inspection Tools

**`symfony-ai-features`** - **USE THIS FIRST** when analyzing or modifying AI configuration
- Detects and lists all AI platforms, agents, tools, stores, vectorizers, indexers, retrievers, and multi-agent setups
- Provides summary counts and detailed configuration information
- Analyzes `config/packages/ai.yaml` and `composer.json`
- Use when: Starting any AI-related task, debugging agent configuration, understanding project AI capabilities

```
Example: Before modifying agents, call symfony-ai-features to understand current setup
```

**`symfony-services`** - Get complete list of Symfony services
- Lists all registered services in the DI container
- Use when: Debugging dependency injection, finding service IDs, understanding available services

### Monolog/Logging Tools

**`monolog-search`** - Search logs by text term
- Parameters: `term` (required), `level`, `channel`, `environment`, `from`, `to`, `limit` (default: 100)
- Use when: Debugging errors, finding specific log messages, tracking events

**`monolog-search-regex`** - Search logs using regex patterns
- Parameters: `pattern` (required), `level`, `channel`, `environment`, `limit`
- Use when: Complex log searching, pattern matching, finding formatted data

**`monolog-context-search`** - Search logs by context field
- Parameters: `key` (required), `value` (required), `level`, `environment`, `limit`
- Use when: Finding logs with specific context data (user_id, request_id, etc.)

**`monolog-tail`** - Get last N log entries (like `tail -f`)
- Parameters: `lines` (default: 50), `level`, `environment`
- Use when: Checking recent activity, monitoring real-time logs, debugging current issues

**`monolog-list-files`** - List available log files
- Parameters: `environment` (optional)
- Use when: Finding log locations, checking available environments

**`monolog-list-channels`** - List all log channels
- Use when: Understanding logging structure, finding channel names for filtering

**`monolog-by-level`** - Filter logs by severity level
- Parameters: `level` (required: DEBUG, INFO, WARNING, ERROR, CRITICAL, etc.), `environment`, `limit`
- Use when: Finding errors, warnings, or specific severity issues

### System Information Tools

**`php-version`** - Get PHP version
- Use when: Checking compatibility, debugging version-specific issues

**`php-extensions`** - List installed PHP extensions
- Use when: Verifying required extensions, debugging extension-related issues

**`operating-system`** - Get current OS
- Use when: Platform-specific debugging, environment verification

**`operating-system-family`** - Get OS family (Windows, Linux, Darwin)
- Use when: Cross-platform compatibility checks

### Tool Usage Guidelines

**Proactive Usage Patterns**:
1. **Before modifying AI config**: Call `symfony-ai-features` to understand current setup
2. **When debugging errors**: Use `monolog-tail` and `monolog-search` to find relevant logs
3. **When analyzing agents**: Use `symfony-ai-features` to see all agents, tools, and configurations
4. **When troubleshooting**: Combine log tools with system info tools for complete context
5. **When adding new features**: Check existing services with `symfony-services`

**Example Workflows**:

```bash
# Investigating AI agent issues
1. symfony-ai-features (get agent configuration)
2. monolog-search term:"agent" (find agent-related logs)
3. monolog-by-level level:"ERROR" (check for errors)

# Debugging application errors
1. monolog-tail lines:100 (recent activity)
2. monolog-by-level level:"ERROR" (find errors)
3. monolog-context-search key:"exception" (error details)

# Understanding project structure
1. symfony-ai-features (AI capabilities)
2. symfony-services (available services)
3. php-extensions (installed extensions)
```

## Development Notes

- Uses PHP 8.4+ with strict typing and modern PHP features
- All AI agents use OpenAI GPT-4o-mini by default
- Vector embeddings use OpenAI's text-ada-002 model
- ChromaDB runs on port 8080 (mapped from container port 8000)
- Application follows Symfony best practices with dependency injection
- LiveComponents provide real-time UI updates without custom JavaScript
- MCP server enables tool integration for AI agents