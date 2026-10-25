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
- `src/EventListener/`: Event listeners (including `TemplateRendererListener`)

### Provider Support
Bridge implementations for:
- OpenAI (GPT, DALL-E, Whisper)
- Anthropic (Claude models)
- Azure OpenAI
- Google Gemini, VertexAI
- AWS Bedrock, Ollama
- Many others (see composer.json)

## Architecture Decisions

The design decisions of this package are recorded in `adr/` (index in `adr/README.md`).
Read them before changing contracts or extension points, and add a new ADR for every new
architecturally significant decision (see the root `adr/README.md`).

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

// UserMessage with mixed content (text and template)
$message = Message::ofUser(
    'Plain text',
    Template::string('and {dynamic} content')
);

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

## Testing bridges: record & replay

Result converters are the most bug-prone part of a bridge because they must
interpret a provider's real (and evolving) response and streaming-event shapes.
Hand-written fixtures drift from reality, so this component ships record-and-replay
scaffolding in `src/Test/Replay/` (production-autoloaded, reusable by every bridge):

The building blocks are `Test\Replay\HttpCassette` (redacted on-disk recording) and
`Test\Replay\CassetteHttpClient` (records through a real client, replays FIFO through a
`MockHttpClient`) — see the "Testing" section of `docs/components/platform.rst`.

- **Record** (occasional, real API, local only): the whole `examples/` corpus is the
  request corpus. A maintainer runs `examples/runner --record openai` against their
  own API keys, and `CassetteHttpClient` captures each interaction — including raw
  SSE streams, with binary bodies elided to metadata stubs — into a redacted `HttpCassette`
  (`examples/tests/fixtures/<path>.json`), refreshing the replay goldens afterwards; both
  are then committed. Recording never runs in CI: it needs credentials for every
  provider, which CI does not have.
- **Replay** (always, CI, no keys): `examples/tests/ExamplesReplayTest` re-runs each
  example offline (a `CassetteHttpClient` serving the cassette, `CASSETTE=replay`)
  and asserts it still succeeds against its committed golden output. Because the
  example drives the real pipeline (`Platform → ModelClient → RawHttpResult →
  ResultConverter`), a converter regression fails here.
- **Targeted assertions**: extend `Test\Replay\AbstractBridgeReplayTestCase` (see
  `Bridge/OpenResponses/Tests/ReplayTest`) to assert precise stream deltas / exception
  types on committed cassettes when golden-stdout is too coarse.

When adding or changing a converter, record (or hand-seed) a cassette for the new
provider behavior so the replay tests lock it in.
