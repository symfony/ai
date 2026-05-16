# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in the Chat component.

## Component Overview

Library for building chats with agents using messages. Built on Platform and Agent components.

## Architecture

### Core Classes
- **Chat** (`src/Chat.php`): Main orchestration class
- **ChatInterface**: Contract for implementations
- **MessageStoreInterface** High-level conversation storage interface
- **ManagedStoreInterface** High-level store management interface

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

### Built-in message stores
- **InMemory** (`src/InMemory/Store.php`): in-memory storage, not a bridge
- **Cache bridge** (`src/Bridge/Cache/MessageStore.php`): PSR-6 cache-backed storage
- **Session bridge** (`src/Bridge/Session/MessageStore.php`): Symfony HttpFoundation session storage
- Additional bridges under `src/Bridge/`: Cloudflare, Doctrine, Meilisearch, MongoDb, Pogocache, Redis, SurrealDb

## Dependencies

- **Platform component**: Required for AI communication
- **Agent component**: Required for agent interaction
- **Symfony**: HttpFoundation

## Testing Patterns

- Use `MockHttpClient` over response mocking
- Test bridges independently
- Prefer `$this->assert*` over `self::assert*` in tests

## Development Notes

- All new classes should have `@author` tags
- Use component-specific exceptions from `src/Exception/`
- Follow Symfony coding standards with `@Symfony` PHP CS Fixer rules
- The component is marked as experimental and subject to BC breaks
