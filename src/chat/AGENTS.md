# AGENTS.md

AI agent guidance for the Chat component.

## Component Overview

Library for building chats with agents using messages. Built on Platform and Agent components.

## Architecture

### Core Classes
- **Chat** (`src/Chat.php`): Main orchestration class
- **ChatInterface**: Contract for implementations
- **MessageStoreInterface** High-level conversation storage interface

### Key Features
- **Bridge** (`src/Bridge/`): Storage capacity for messages and conversations

## Essential Commands

### Testing
```bash
vendor/bin/phpunit
vendor/bin/phpunit tests/ChatTest.php
vendor/bin/phpunit --coverage-html coverage/
```

### Code Quality
```bash
vendor/bin/phpstan analyse
cd ../../.. && vendor/bin/php-cs-fixer fix src/chat/
```

## Processing Architecture

### Built-in bridges
- **CacheStore**: PSR-16 compliant storage
- **InMemoryStore**: In-memory storage
- **SessionStore**: Symfony HttpFoundation session storage

## Dependencies

- **Platform component**: Required for AI communication
- **Agent component**: Required for agent interaction
- **Symfony**: HttpFoundation

## Testing Patterns

- Use `MockHttpClient` over response mocking
- Test bridges independently
- Prefer `self::assert*` in tests

## Development Notes

- Component is experimental (BC breaks possible)
- Add `@author` tags to new classes
- Use component-specific exceptions from `src/Exception/`
- Follow `@Symfony` PHP CS Fixer rules
