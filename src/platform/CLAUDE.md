# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Platform Component Overview

This is the Platform component of the Symfony AI monorepo - a unified abstraction for interacting with AI platforms like OpenAI, Anthropic, Azure, Gemini, VertexAI, Ollama, and others. The component provides consistent interfaces regardless of the underlying AI provider.

## Development Commands

### Testing
```bash
# Run all tests
vendor/bin/phpunit

# Run specific test
vendor/bin/phpunit tests/ModelTest.php

# Run tests with coverage
vendor/bin/phpunit --coverage-html coverage
```

### Code Quality
```bash
# Run PHPStan static analysis
vendor/bin/phpstan analyse

# Fix code style (run from project root)
cd ../../.. && vendor/bin/php-cs-fixer fix src/platform/
```

### Installing Dependencies
```bash
composer install

# Update dependencies
composer update
```

## Architecture

### Core Classes
- **Platform**: Main entry point implementing `PlatformInterface`
- **Model**: Represents AI models with provider-specific configurations
- **Contract**: Abstract contracts for different AI capabilities (chat, embedding, speech, etc.)
- **Message**: Message system for AI interactions
- **Tool**: Function calling capabilities
- **Bridge**: Provider-specific implementations (OpenAI, Anthropic, etc.)

### Key Directories
- `src/Bridge/`: Provider-specific implementations
- `src/Contract/`: Abstract contracts and interfaces  
- `src/Message/`: Message handling system
- `src/Tool/`: Function calling and tool definitions
- `src/Result/`: Result types and converters
- `src/Exception/`: Platform-specific exceptions

### Provider Support
The component supports multiple AI providers through Bridge implementations:
- OpenAI (GPT models, DALL-E, Whisper)
- Anthropic (Claude models)
- Azure OpenAI
- Google Gemini
- VertexAI
- AWS Bedrock
- Ollama
- And many others (see composer.json keywords)

## Testing Architecture

- Uses PHPUnit 11+ with strict configuration
- Test fixtures located in `../../fixtures` for multi-modal content
- Mock HTTP client pattern preferred over response mocking
- Component follows Symfony coding standards