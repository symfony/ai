# AGENTS.md

AI agent guidance for the Symfony AI Bundle.

## Component Overview

Symfony integration bundle providing DI configuration for AI components (Platform, Agent, Store). Enables declarative YAML configuration and PHP attributes.

## Architecture

### Core Integration
- **Platform Integration**: AI platforms as Symfony services
- **Agent Configuration**: Declarative agent setup with tools/processors
- **Store Configuration**: Vector stores for document retrieval
- **Security Integration**: `#[IsGrantedTool]` attribute for authorization
- **Profiler Integration**: Debug toolbar for AI interactions

### Key Components
- `AiBundle.php`: Main bundle with service configuration
- `ProcessorCompilerPass.php`: Processor registration
- Security system with `IsGrantedToolAttributeListener`
- Profiler data collector and traceable decorators

## Essential Commands

### Testing
```bash
vendor/bin/phpunit
vendor/bin/phpunit tests/DependencyInjection/AiBundleTest.php
vendor/bin/phpunit --coverage-html coverage/
```

### Code Quality
```bash
vendor/bin/phpstan analyse
# Code style fixes from monorepo root
```

## Configuration Architecture

### Platform Configuration
- Multiple AI providers via factory classes
- Anthropic, OpenAI, Azure OpenAI, Gemini, VertexAI
- HTTP client integration
- Automatic service aliasing

### Agent Configuration
- Model configuration (class, name, options)
- Tool integration via `#[AsTool]` or explicit references
- Input/Output processor chains
- System prompt with optional tool inclusion
- Token usage tracking

### Store Configuration
- Local stores: memory, cache
- Cloud stores: Azure Search, Pinecone, Qdrant
- Database stores: MongoDB, ClickHouse, Neo4j

### Security Integration
- `#[IsGrantedTool]` method-level authorization
- Symfony Security component integration
- Runtime permission checking

## Service Registration

### Attribute-Based
- `#[AsTool]`: Tool registration with name/description
- `#[AsInputProcessor]`: Agent-specific input processing
- `#[AsOutputProcessor]`: Agent-specific output processing

### Interface-Based Autoconfiguration
- `InputProcessorInterface` → `ai.agent.input_processor`
- `OutputProcessorInterface` → `ai.agent.output_processor`
- `ModelClientInterface` → `ai.platform.model_client`

## Debug Features

### Profiler Integration
- Traceable decorators for platforms/toolboxes
- Symfony Profiler data collector
- AI interaction and token usage monitoring

### Error Handling
- Fault-tolerant toolbox wrapper
- Bundle-specific exception hierarchy
- Clear missing dependency error messages

## Testing Patterns

- Bundle configuration testing
- Compiler pass testing
- Security integration with mock checker
- Profiler data collection testing
- PHPUnit 11 with strict configuration