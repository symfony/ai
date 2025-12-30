# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Mate Component Overview

This is the Mate component of the Symfony AI monorepo - an MCP (Model Context Protocol) server that enables AI assistants to interact with Symfony applications. The component is standalone and does not integrate with the AI Bundle.

## Development Commands

### Testing
```bash
# Run all tests
vendor/bin/phpunit

# Run specific test
vendor/bin/phpunit tests/Command/InitCommandTest.php

# Run bridge tests
vendor/bin/phpunit src/Bridge/Symfony/Tests/
vendor/bin/phpunit src/Bridge/Monolog/Tests/
```

### Code Quality
```bash
# Run PHPStan static analysis
vendor/bin/phpstan analyse

# Fix code style (run from monorepo root)
cd ../../.. && vendor/bin/php-cs-fixer fix src/mate/
```

### Running the Server
```bash
# Initialize configuration
bin/mate init

# Discover extensions
bin/mate discover

# Start MCP server
bin/mate serve

# Clear cache
bin/mate clear-cache

# Debug commands
bin/mate debug:capabilities      # Show all MCP capabilities
bin/mate debug:extensions        # Show extension discovery and loading status
```

## Architecture

### Core Classes
- **App**: Console application builder
- **ContainerFactory**: DI container management with extension discovery
- **ComposerExtensionDiscovery**: Discovers MCP extensions via `extra.ai-mate` in composer.json
- **FilteredDiscoveryLoader**: Loads MCP capabilities with feature filtering

### Key Directories
- `src/Command/`: CLI commands (serve, init, discover, clear-cache, debug:capabilities, debug:extensions)
- `src/Container/`: DI container management
- `src/Discovery/`: Extension discovery system
- `src/Capability/`: Built-in MCP tools
- `src/Bridge/`: Embedded bridge packages (Symfony, Monolog)

### Bridges
The component includes embedded bridge packages:

**Symfony Bridge** (`src/Bridge/Symfony/`):
- `ServiceTool`: Symfony container introspection
- `ContainerProvider`: Parses compiled container XML

**Monolog Bridge** (`src/Bridge/Monolog/`):
- `LogSearchTool`: Log search and analysis
- `LogParser`: Parses JSON and standard Monolog formats
- `LogReader`: Reads and filters log files

### Configuration
- `mate/extensions.php`: Enable/disable extensions
- `mate/config.php`: Custom service configuration
- `mate/.env`: Environment variables for mate configuration
- `mate/src/`: Directory for user-defined MCP tools

## Testing Architecture

- Uses PHPUnit 11+ with strict configuration
- Bridge tests are located within their respective bridge directories
- Fixtures for discovery tests in `tests/Discovery/Fixtures/`
- Component follows Symfony coding standards
