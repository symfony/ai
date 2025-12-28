# AGENTS.md

This file provides guidance to AI agents when working with code in this repository.

## Project Overview

Symfony AI monorepo with independent packages for AI integration in PHP applications. Each component in `src/` has its own composer.json, tests, and dependencies.

## Architecture

### Core Components
- **Platform** (`src/platform/`): Unified AI platform interface (OpenAI, Anthropic, Azure, Gemini, VertexAI)
- **Agent** (`src/agent/`): AI agent framework for user interaction and task execution
- **Store** (`src/store/`): Data storage abstraction with vector database support
- **Mate** (`src/mate/`): AI-powered coding assistant for PHP development

### Bridges
Each core component has bridges in `src/<component>/src/Bridge/` that provide integrations with specific third-party services. Bridges are separate Composer packages with their own dependencies and can be installed independently.

### Integration Bundles
- **AI Bundle** (`src/ai-bundle/`): Symfony integration for Platform, Store, and Agent
- **MCP Bundle** (`src/mcp-bundle/`): Symfony integration for official MCP SDK

### Supporting Directories
- **Examples** (`examples/`): Standalone usage examples
- **Demo** (`demo/`): Full Symfony web application demo
- **Fixtures** (`fixtures/`): Shared multi-modal test fixtures

## Essential Commands

### Testing
```bash
# Component-specific testing
cd src/platform && vendor/bin/phpunit
cd src/agent && vendor/bin/phpunit
cd src/ai-bundle && vendor/bin/phpunit
cd demo && vendor/bin/phpunit
```

### Code Quality
```bash
# Fix code style (always run after changes)
vendor/bin/php-cs-fixer fix

# Static analysis (component-specific)
cd src/platform && vendor/bin/phpstan analyse
```

### Development Tools
```bash
# Link components for development
./link /path/to/project

# Run examples
cd examples && php anthropic/chat.php

# Demo application
cd demo && symfony server:start
```

## Code Standards

### PHP Conventions
- Follow Symfony coding standards with `@Symfony` PHP CS Fixer rules
- Use project-specific exceptions instead of global ones (`\RuntimeException`, `\InvalidArgumentException`)
- Define array shapes for parameters and return types
- Add `@author` tags to new classes
- Always add newlines at end of files

### Testing Guidelines
- Use **PHPUnit 11+** with component-specific configurations
- Prefer `MockHttpClient` over response mocking
- Use `self::assert*` or `$this->assert*` in tests
- No void return types for test methods
- Leverage shared fixtures in `/fixtures` for multi-modal content
- Always fix risky tests

### Git & Commits
- Never mention AI assistance in commits or PR descriptions
- Sign commits with GPG
- Use conventional commit messages

### Variable Naming
- Name MessageBus variables as `$bus` (not `$messageBus`)

## Component Dependencies

- Agent → Platform (AI communication)
- AI Bundle → Platform + Agent + Store (integration)
- MCP Bundle → MCP SDK (integration)
- Store: standalone (often used with Agent for RAG)

## Development Workflow

1. Each `src/` component is independently versioned
2. Run PHP-CS-Fixer after code changes
3. Test component-specific changes in isolation
4. Use monorepo structure for shared development workflow

## Version Documentation

### UPGRADE.md
- Document breaking changes in the root `UPGRADE.md` file
- Format: Use version headers like `UPGRADE FROM 0.X to 0.Y` with sections per component
- Include code examples showing before/after changes with diff syntax

### CHANGELOG.md
- Each component has its own `CHANGELOG.md` in its root directory
- Add entries for new features, and deprecations under the appropriate version heading
- Format entries as bullet points starting with "Add", "Fix", "Deprecate", etc.

