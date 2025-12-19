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
- **Template**: Message templating with pluggable rendering strategies
- **Tool**: Function calling capabilities
- **Bridge**: Provider-specific implementations

### Key Directories
- `src/Bridge/`: Provider implementations
- `src/Contract/`: Abstract contracts and interfaces
- `src/Message/`: Message handling system with Template support
- `src/Message/TemplateRenderer/`: Template rendering strategies
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

## Usage Patterns

### Message Templates

Templates support variable substitution with type-based rendering. SystemMessage and UserMessage support templates.

```php
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\Template;

// SystemMessage with template
$template = Template::string('You are a {role} assistant.');
$message = Message::forSystem($template);

// UserMessage with template
$message = Message::ofUser(Template::string('Calculate {operation}'));

// Multiple messages with templates
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

Rendering happens externally during `Platform.invoke()` when `template_vars` option is provided.

## Development Notes

- PHPUnit 11+ with strict configuration
- Test fixtures in `../../fixtures` for multimodal content
- MockHttpClient pattern preferred
- Follows Symfony coding standards
- Bridge pattern for provider implementations
- Consistent contract interfaces across providers
- Template system uses type-based rendering (not renderer injection)
- Template rendering via TemplateRendererListener during invocation