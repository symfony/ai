# AGENTS.md

AI agent guidance for the Mate component.

## Component Overview

Standalone MCP (Model Context Protocol) server enabling AI assistants to interact with Symfony applications. Does not integrate with the AI Bundle.

## Architecture

### Core Classes
- **App**: Console application builder
- **ContainerFactory**: DI container management with extension discovery
- **ComposerExtensionDiscovery**: Discovers MCP extensions via `extra.ai-mate` in composer.json
- **FilteredDiscoveryLoader**: Loads MCP capabilities with feature filtering

### Key Directories
- `src/Command/`: CLI commands (serve, init, discover, clear-cache, debug:*, mcp:*)
- `src/Container/`: DI container management
- `src/Discovery/`: Extension discovery system
- `src/Capability/`: Built-in MCP tools
- `src/Bridge/`: Embedded bridge packages (Symfony, Monolog)

### Bridges
- **Symfony Bridge** (`src/Bridge/Symfony/`): Container introspection and profiler access
- **Monolog Bridge** (`src/Bridge/Monolog/`): Log search and analysis

## Essential Commands

### Testing
```bash
vendor/bin/phpunit
vendor/bin/phpunit tests/Command/InitCommandTest.php
vendor/bin/phpunit src/Bridge/Symfony/Tests/
```

### Code Quality
```bash
vendor/bin/phpstan analyse
cd ../../.. && vendor/bin/php-cs-fixer fix src/mate/
```

### Running the Server
```bash
bin/mate init                               # Initialize configuration
bin/mate discover                           # Discover extensions
bin/mate serve                              # Start MCP server
bin/mate clear-cache                        # Clear cache

bin/mate debug:capabilities                 # Show all MCP capabilities
bin/mate debug:extensions                   # Show extension discovery status

bin/mate mcp:tools:list                     # List MCP tools
bin/mate mcp:tools:inspect server-info      # Inspect tool with schema
bin/mate mcp:tools:call server-info '{}'    # Execute tool
bin/mate mcp:resources:read <uri>           # Read a resource by URI
```

## Agent Instructions Materialization

Running `bin/mate discover` generates `mate/AGENT_INSTRUCTIONS.md` with extension-specific instructions and maintains a managed block in `AGENTS.md` with a summary of installed extensions. AI agents should read these files to learn about available MCP tools rather than relying on hardcoded tool lists.

## Configuration

- `mate/extensions.php`: Enable/disable extensions
- `mate/config.php`: Custom service configuration
- `mate/.env`: Environment variables for mate configuration
- `mate/src/`: Directory for user-defined MCP tools

### Extension Exclusion

By default, `mate init` sets `extension: false` in `composer.json` so vendor packages using Mate for internal tooling are not detected as user-facing extensions. To make a package a discoverable Mate extension, set `"extension": true` or remove the field entirely.

## Testing Patterns

- Uses PHPUnit 11+ with strict configuration
- Bridge tests live within their respective bridge directories
- Fixtures for discovery tests in `tests/Discovery/Fixtures/`
- Follow Symfony coding standards

## MCP Tool & Resource Design Principles

Every tool and resource in Mate serves one purpose: giving an AI assistant exactly the context
it needs to act — no more, no less. The data sources Mate taps into (profiler, container,
logs, …) expose far more information than any AI needs in one turn. The tool layer's job is
to distill that information, not relay it.

### Distillation over completeness

An AI context window is a limited, expensive resource. Every field, every byte returned by a
tool must earn its place by changing what the AI would diagnose or decide.

- Prefer aggregated counts over full item lists when counts tell the story
- Drop fields the AI cannot act on
- If removing a field does not change the diagnosis, remove it

### Truncation is intentional, not a limitation

Tools that can return unbounded data (log lines, queries, events, services, …) must enforce
a hard upper limit and signal when the result is truncated. The AI can ask again with a
narrower filter — it cannot unsee a 50 KB context dump.

Always pair a truncation limit with a `_truncated: bool` (or equivalent) so the AI knows
it is reasoning over a sample, not the full picture.

### Sensitive data stays out

Tools run in development environments, but they touch data from real users and real systems.
Passwords, session tokens, auth headers, API keys, DTO payload values, and sensitive env vars
must be excluded or redacted. Prefer omission over masking when a field is not needed for
diagnosis.

### Format for reasoning, not for display

The AI will reason over the data, not render it.

- Use human-readable strings instead of numeric codes (`'missing'` not `1`)
- Use consistent units and round values (milliseconds rounded to 2 decimals, not raw floats)
- Use class short names or identifiers where full FQCNs add no diagnostic value
- Prefer flat structures over deeply nested objects

### The triage → detail pattern

Where a tool or resource exposes a two-level API (summary then full data), design both levels
consciously:

- The summary level answers: *is this source relevant to the current problem?*
- The full-data level answers: *what exactly went wrong?*

The AI reads summaries first to triage, then fetches full data only for what matters. Design
both levels to support this flow.

## Development Notes

- Do not use void return type for testcase methods
- Add `@author` tags to new classes
- Use component-specific exceptions instead of `\RuntimeException`, `\InvalidArgumentException`, etc.
- Avoid `empty()`; prefer explicit checks like `[] === $array`, `'' === $string`, `null === $value`
- Prefer classic `if` statements over short-circuit evaluation
- Define array shapes for parameters and return types
- Always end files with a newline
