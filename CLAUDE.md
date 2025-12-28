# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is the Symfony AI monorepo containing multiple components and bundles that integrate AI capabilities into PHP applications. The project is organized as a collection of independent packages under the `src/` directory, each with their own composer.json, tests, and dependencies.

## Architecture

### Core Components
- **Platform** (`src/platform/`): Unified interface to AI platforms (OpenAI, Anthropic, Azure, Gemini, VertexAI, etc.)
- **Agent** (`src/agent/`): Framework for building AI agents that interact with users and perform tasks
- **Store** (`src/store/`): Data storage abstraction with indexing and retrieval for vector databases
- **Mate** (`src/mate/`): AI-powered coding assistant for PHP development

### Bridges
Each core component has bridges in `src/<component>/src/Bridge/` that provide integrations with specific third-party services. Bridges are separate Composer packages with their own dependencies and can be installed independently.

### Integration Bundles
- **AI Bundle** (`src/ai-bundle/`): Symfony integration for Platform, Store, and Agent components
- **MCP Bundle** (`src/mcp-bundle/`): Symfony integration for official MCP SDK

### Supporting Directories
- **Examples** (`examples/`): Standalone examples demonstrating component usage across different AI platforms
- **Demo** (`demo/`): Full Symfony web application showcasing components working together
- **Fixtures** (`fixtures/`): Shared test fixtures for multi-modal testing (images, audio, PDFs)

## Development Commands

### Testing
Each component has its own test suite. Run tests for specific components:
```bash
# Platform component
cd src/platform && vendor/bin/phpunit

# Agent component
cd src/agent && vendor/bin/phpunit

# AI Bundle
cd src/ai-bundle && vendor/bin/phpunit

# Demo application
cd demo && vendor/bin/phpunit
```

### Code Quality
The project uses PHP CS Fixer with Symfony coding standards:
```bash
# Fix code style issues
vendor/bin/php-cs-fixer fix

# Check specific directories
vendor/bin/php-cs-fixer fix src/platform/
```

Static analysis with PHPStan (component-specific):
```bash
cd src/platform && vendor/bin/phpstan analyse
```

### Development Linking
Use the `./link` script to symlink local development versions:
```bash
# Link components to external project
./link /path/to/project

# Copy instead of symlink
./link --copy /path/to/project

# Rollback changes
./link --rollback /path/to/project
```

### Running Examples
Examples are self-contained and can be run individually:
```bash
cd examples
php anthropic/chat.php
php openai/function-calling.php
```

Many examples require environment variables (see `.env` files in example directories).

### Demo Application
The demo is a full Symfony application:
```bash
cd demo
composer install
symfony server:start
```

## Component Dependencies

Components are designed to work independently but have these relationships:
- Agent depends on Platform for AI communication
- AI Bundle integrates Platform, Agent, and Store
- MCP Bundle provides official MCP SDK integration
- Store is standalone but often used with Agent for RAG applications

## Testing Architecture

Each component uses:
- **PHPUnit 11+** for testing framework
- Component-specific `phpunit.xml.dist` configurations
- Shared fixtures in `/fixtures` for multi-modal content
- MockHttpClient pattern preferred over response mocking

## Development Notes

- Each component in `src/` is a separate Composer package with its own dependencies
- Components follow Symfony coding standards and use `@Symfony` PHP CS Fixer rules
- The monorepo structure allows independent versioning while maintaining shared development workflow
- Do not use void return type for testcase methods
- Always run PHP-CS-Fixer to ensure proper code style
- Always add a newline at the end of the file
- Prefer self::assert* over $this->assert* in tests
- Never add Claude as co-author in the commits
- Add @author tags to newly introduced classes by the user
- Prefer classic if statements over short-circuit evaluation when possible
- Define array shapes for parameters and return types
- Use project specific exceptions instead of global exception classes like \RuntimeException, \InvalidArgumentException etc.
- NEVER mention Claude as co-author in commits

## Version Documentation

### UPGRADE.md
- Document breaking changes in the root `UPGRADE.md` file
- Format: Use version headers like `UPGRADE FROM 0.X to 0.Y` with sections per component
- Include code examples showing before/after changes with diff syntax

### CHANGELOG.md
- Each component has its own `CHANGELOG.md` in its root directory
- Add entries for new features, and deprecations under the appropriate version heading
- Format entries as bullet points starting with "Add", "Fix", "Deprecate", etc.

