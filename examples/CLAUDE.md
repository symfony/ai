# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is the examples directory of the Symfony AI monorepo, containing standalone examples demonstrating component usage across different AI platforms. The examples serve as both reference implementations and integration tests.

## Development Commands

### Setup
```bash
# Install dependencies
composer install

# Link local AI components for development
../link

# Start Docker services for store examples
docker compose up -d
```

### Running Examples

#### Standalone Examples
```bash
# Run a specific example
php openai/chat.php

# Run with verbose output to see HTTP and tool calls
php openai/toolcall-stream.php -vvv
```

#### Example Runner
```bash
# Run all examples in parallel
./runner

# Run examples from specific subdirectories
./runner openai mistral

# Filter examples by name pattern
./runner --filter=toolcall
```

### Environment Configuration
Examples require API keys configured in `.env.local`. Copy from `.env` template and add your keys for the platforms you want to test.

## Architecture

### Directory Structure
- Each subdirectory represents a different AI platform (openai/, anthropic/, gemini/, etc.)
- `misc/` contains cross-platform examples
- `rag/` contains RAG (Retrieval Augmented Generation) examples
- `toolbox/` contains utility tools and integrations
- `bootstrap.php` provides common setup and utilities for all examples

### Common Patterns
- All examples use the shared `bootstrap.php` for setup
- Examples follow a consistent structure with platform-specific clients
- Verbose output (`-vv`, `-vvv`) shows detailed HTTP requests and responses
- Examples demonstrate both synchronous and streaming capabilities

### Dependencies
Examples use `@dev` versions of Symfony AI components:
- `symfony/ai-platform`
- `symfony/ai-agent` 
- `symfony/ai-store`

## Testing
Examples serve as integration tests. The runner executes them in parallel to verify all components work correctly across different platforms.