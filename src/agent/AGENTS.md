# AGENTS.md

AI agent guidance for the Agent component.

## Component Overview

Framework for building AI agents with user interaction and task execution. Built on Platform component with optional Store integration for memory.

## Architecture

### Core Classes
- **Agent** (`src/Agent.php`): Main orchestration class
- **AgentInterface**: Contract for implementations
- **Context** (`src/Context/`): Data objects handed to the agent, plus the `AgentRequest`/`AgentResult` envelopes
- **Execution** (`src/Execution/`): Lazy, iterable stream of `Progress` and `Result` updates returned by `run()`

### Processing Pipeline
- **ContextProcessorInterface**: Request transformation contract, selected per context item type
- **ResultAwareContextProcessorInterface**: Additionally inspects or replaces the result
- Middleware-like processing chain

### Key Features
- **Memory** (`src/Memory/`): Conversation memory with embeddings
- **Toolbox** (`src/Toolbox/`): Function calling capabilities
- **MultiAgent** (`src/MultiAgent/`): Orchestration across multiple specialized agents
- **Bridge** (`src/Bridge/`): Third-party tool integrations (Brave, Tavily, Wikipedia, etc.)

## Essential Commands

### Testing
```bash
vendor/bin/phpunit
vendor/bin/phpunit tests/AgentTest.php
vendor/bin/phpunit --coverage-html coverage/
```

### Code Quality
```bash
vendor/bin/phpstan analyse
cd ../../.. && vendor/bin/php-cs-fixer fix src/agent/
```

## Processing Architecture

### Pipeline Flow
1. Context processors modify the request
2. Platform processes the request, the runner drives the tool-calling loop
3. Result-aware context processors modify the result

### Built-in Processors
- **InstructionProcessor**: Adds the instruction (system prompt)
- **AttachmentProcessor**: Attaches context content to the user message
- **ToolProcessor**: Exposes the toolbox to the model
- **MemoryProcessor**: Conversation context from memory

### Memory Providers
- **StaticMemoryProvider**: In-memory storage
- **EmbeddingProvider**: Vector-based semantic memory (requires Store)

### Tool Integration
- Auto-discovery via attributes
- Fault-tolerant execution
- Event system for lifecycle management

## Dependencies

- **Platform component**: Required for AI communication
- **Store component**: Optional for embedding memory
- **Symfony**: HttpClient, Serializer, PropertyAccess, Clock

## Testing Patterns

- Use `MockHttpClient` over response mocking
- Test processors independently
- Use `/fixtures` for multimodal content
- Prefer `$this->assert*` over `self::assert*` in tests

## Development Notes

- Component is experimental (BC breaks possible)
- Add `@author` tags to new classes
- Use component-specific exceptions from `src/Exception/`
- Follow `@Symfony` PHP CS Fixer rules
