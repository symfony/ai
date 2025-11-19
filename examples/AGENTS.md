# AGENTS.md

AI agent guidance for Symfony AI examples directory.

## Project Overview

Standalone examples demonstrating Symfony AI component usage across different platforms. Serves as reference implementations and integration tests.

## Essential Commands

### Setup
```bash
composer install
../link  # Link local AI components
docker compose up -d  # For store examples
```

### Running Examples
```bash
# Single example
php openai/chat.php
php openai/toolcall-stream.php -vvv

# Batch execution
./runner  # All examples
./runner openai mistral  # Specific platforms
./runner --filter=toolcall  # Pattern filter
```

### Environment
Configure API keys in `.env.local` (copy from `.env` template).

## Architecture

### Directory Structure
- Platform directories: `openai/`, `anthropic/`, `gemini/`, etc.
- `misc/`: Cross-platform examples
- `rag/`: Retrieval Augmented Generation examples
- `toolbox/`: Utility tools and integrations
- `bootstrap.php`: Common setup for all examples

### Patterns
- Shared `bootstrap.php` setup
- Consistent structure across platforms
- Verbose output flags (`-vv`, `-vvv`)
- Synchronous and streaming demos

### Dependencies
Uses `@dev` versions:
- `symfony/ai-platform`
- `symfony/ai-agent`
- `symfony/ai-store`

## Development Notes

- Examples serve as integration tests
- Runner executes in parallel for platform verification
- Demonstrates both sync and async patterns
- Platform-specific client configurations