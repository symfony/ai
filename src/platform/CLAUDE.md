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
- **Template**: Message templating with type-based rendering strategies
- **Tool**: Function calling capabilities
- **Bridge**: Provider-specific implementations (OpenAI, Anthropic, etc.)

### Key Directories
- `src/Bridge/`: Provider-specific implementations
- `src/Contract/`: Abstract contracts and interfaces
- `src/Message/`: Message handling system with Template support
- `src/Message/TemplateRenderer/`: Template rendering strategies
- `src/Tool/`: Function calling and tool definitions
- `src/Result/`: Result types and converters
- `src/Exception/`: Platform-specific exceptions
- `src/EventListener/`: Event listeners (including TemplateRendererListener)

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

## Usage Examples

### Message Templates

Templates support variable substitution with type-based rendering. SystemMessage and UserMessage support templates:

```php
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\Template;

// SystemMessage with template
$template = Template::string('You are a {role} assistant.');
$message = Message::forSystem($template);

// UserMessage with template
$message = Message::ofUser(Template::string('Calculate {operation}'));

// UserMessage with mixed content (text and template)
$message = Message::ofUser(
    'Plain text',
    Template::string('and {dynamic} content')
);

// Multiple messages
$messages = new MessageBag(
    Message::forSystem(Template::string('You are a {role} assistant.')),
    Message::ofUser(Template::string('Calculate {operation}'))
);

$result = $platform->invoke('gpt-4o-mini', $messages, [
    'template_vars' => [
        'role' => 'helpful',
        'operation' => '2 + 2',
    ],
]);

// Expression template (requires symfony/expression-language)
$template = Template::expression('price * quantity');
```

Templates are rendered during `Platform.invoke()` when `template_vars` option is provided.

## Testing Architecture

- Uses PHPUnit 11+ with strict configuration
- Test fixtures located in `../../fixtures` for multi-modal content
- Mock HTTP client pattern preferred over response mocking
- Component follows Symfony coding standards
- Template tests cover all renderer types and integration scenarios
