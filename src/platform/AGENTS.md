# AGENTS.md

AI agent guidance for the Platform component.

## Component Overview

Unified abstraction for AI platforms (OpenAI, Anthropic, Azure, Gemini, VertexAI, Ollama, etc.). Provides consistent interfaces regardless of provider.

## Architecture

### Core Classes
- **Platform**: Main entry point implementing `PlatformInterface`
- **Model**: AI models with provider-specific configurations
- **Contract**: Abstract contracts for AI capabilities (chat, embedding, speech)
- **Message**: Message system for AI interactions
- **Tool**: Function calling capabilities
- **Bridge**: Provider-specific implementations

### Key Directories
- `src/Bridge/`: Provider implementations
- `src/Contract/`: Abstract contracts and interfaces
- `src/Message/`: Message handling system
- `src/Tool/`: Function calling and tool definitions
- `src/Result/`: Result types and converters
- `src/Exception/`: Platform-specific exceptions

### Provider Support
Bridge implementations for:
- OpenAI (GPT, DALL-E, Whisper)
- Anthropic (Claude models)
- Azure OpenAI
- Google Gemini, VertexAI
- AWS Bedrock, Ollama
- Many others (see composer.json)

## Essential Commands

### Testing
```bash
vendor/bin/phpunit
vendor/bin/phpunit tests/ModelTest.php
vendor/bin/phpunit --coverage-html coverage
```

### Code Quality
```bash
vendor/bin/phpstan analyse
cd ../../.. && vendor/bin/php-cs-fixer fix src/platform/
```

### Dependencies
```bash
composer install
composer update
```

## Development Notes

- PHPUnit 11+ with strict configuration
- Test fixtures in `../../fixtures` for multimodal content
- MockHttpClient pattern preferred
- Follows Symfony coding standards
- Bridge pattern for provider implementations
- Consistent contract interfaces across providers