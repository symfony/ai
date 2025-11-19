# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

The Symfony AI Bundle is an integration bundle that provides Symfony dependency injection configuration for the Symfony AI components (Platform, Agent, Store). It enables declarative configuration of AI agents, platforms, vector stores, and indexers through semantic YAML configuration and PHP attributes.

## Architecture

### Core Integration Points
- **Platform Integration**: Configures AI platforms (OpenAI, Anthropic, Azure, Gemini, etc.) as services
- **Agent Configuration**: Sets up AI agents with tools, processors, and system prompts
- **Store Configuration**: Configures vector stores for document retrieval (ChromaDB, Pinecone, etc.)
- **Security Integration**: Provides `#[IsGrantedTool]` attribute for tool-level authorization
- **Profiler Integration**: Adds debug toolbar integration for monitoring AI interactions

### Key Components
- `AiBundle.php`: Main bundle class handling service configuration and compiler passes
- `ProcessorCompilerPass.php`: Compiler pass for registering input/output processors
- Security system with `IsGrantedToolAttributeListener` for runtime permission checks
- Profiler data collector and traceable decorators for debugging

## Development Commands

### Testing
Run the test suite using PHPUnit 11:
```bash
vendor/bin/phpunit
```

Run specific test file:
```bash
vendor/bin/phpunit tests/DependencyInjection/AiBundleTest.php
```

Run tests with coverage:
```bash
vendor/bin/phpunit --coverage-html coverage/
```

### Static Analysis
Run PHPStan analysis:
```bash
vendor/bin/phpstan analyse
```

The bundle uses PHPStan level 6 and includes custom extension rules for Symfony AI components.

### Code Quality
This bundle follows the parent monorepo's PHP CS Fixer configuration. Code style fixes should be run from the monorepo root.

## Configuration Architecture

The bundle processes configuration through several main sections:

### Platform Configuration
Supports multiple AI platforms through factory classes:
- Anthropic, OpenAI, Azure OpenAI, Gemini, Vertex AI
- Each platform creates a `Platform` service with HTTP client integration
- Automatic service aliasing when only one platform is configured

### Agent Configuration
Creates `Agent` services with:
- Model configuration (class, name, options)
- Tool integration via `#[AsTool]` attribute or explicit service references
- Input/Output processor chains for request/response handling
- System prompt configuration with optional tool inclusion
- Token usage tracking for supported platforms

### Store Configuration
Supports vector stores for document retrieval:
- Local stores (memory, cache)
- Cloud stores (Azure Search, Pinecone, Qdrant)
- Database stores (MongoDB, ClickHouse, Neo4j)

### Security Integration
- `#[IsGrantedTool]` attribute for method-level authorization
- Integration with Symfony Security component
- Runtime permission checking through event listeners

## Service Registration Patterns

### Attribute-Based Registration
The bundle automatically registers services tagged with:
- `#[AsTool]` - Tool registration with name and description
- `#[AsInputProcessor]` - Input processor for specific agents
- `#[AsOutputProcessor]` - Output processor for specific agents

### Interface-Based Autoconfiguration
Automatic tagging for:
- `InputProcessorInterface` → `ai.agent.input_processor`
- `OutputProcessorInterface` → `ai.agent.output_processor`
- `ModelClientInterface` → `ai.platform.model_client`

## Debug and Development Features

### Profiler Integration
In debug mode, the bundle provides:
- Traceable decorators for platforms and toolboxes
- Data collector for Symfony Profiler toolbar
- Monitoring of AI interactions and token usage

### Error Handling
- Fault-tolerant toolbox wrapper for graceful tool failures
- Comprehensive exception hierarchy with bundle-specific exceptions
- Clear error messages for missing dependencies

## Testing Patterns

The test suite demonstrates:
- Bundle configuration testing with `AiBundleTest`
- Compiler pass testing for processor registration
- Security integration testing with mock authorization checker
- Profiler data collection and tracing functionality

Tests use PHPUnit 11 with strict configuration and coverage requirements.