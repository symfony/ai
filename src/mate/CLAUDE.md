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

# Tool introspection
bin/mate mcp:tools:list          # List all available MCP tools
bin/mate mcp:tools:list --filter="search*"  # Filter tools by name pattern
bin/mate mcp:tools:list --format=json       # Output in JSON format
bin/mate mcp:tools:inspect php-version      # Inspect specific tool with schema
bin/mate mcp:tools:inspect php-version --format=json  # Output in JSON format

# Tool execution
bin/mate mcp:tools:call php-version '{}'    # Execute tool with parameters
bin/mate mcp:tools:call php-version '{}' --format=json    # JSON output format
```

## Architecture

### Core Classes
- **App**: Console application builder
- **ContainerFactory**: DI container management with extension discovery
- **ComposerExtensionDiscovery**: Discovers MCP extensions via `extra.ai-mate` in composer.json
- **FilteredDiscoveryLoader**: Loads MCP capabilities with feature filtering

### Key Directories
- `src/Command/`: CLI commands (serve, init, discover, clear-cache, debug:capabilities, debug:extensions, mcp:tools:list, mcp:tools:inspect, mcp:tools:call)
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

### Extension Exclusion

**By default**, when you run `mate init`, the command sets `extension: false` in `composer.json`:

```json
{
  "extra": {
    "ai-mate": {
      "extension": false,
      "scan-dirs": ["mate/src"],
      "includes": ["mate/config.php"]
    }
  }
}
```

**Use Case:** This is useful when a vendor package (e.g., `sulu/sulu`) uses Mate for internal development tooling but should not be detected as a user-facing extension. When the package is installed in other projects, it will be completely hidden from extension discovery.

**Creating a Mate Extension:** If you want your package to BE a Mate extension that other projects can discover and use, either:
- Set `"extension": true` in your `composer.json`, or
- Remove the `"extension"` field entirely (defaults to being discovered)

## Testing Architecture

- Uses PHPUnit 11+ with strict configuration
- Bridge tests are located within their respective bridge directories
- Fixtures for discovery tests in `tests/Discovery/Fixtures/`
- Component follows Symfony coding standards
