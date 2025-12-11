# AGENTS.md

AI agent guidance for the Agent component.

## Component Overview

Framework for building AI agents with user interaction and task execution. Built on Platform component with optional Store integration for memory.

## Architecture

### Core Classes
- **Agent** (`src/Agent.php`): Main orchestration class
- **AgentInterface**: Contract for implementations
- **Input/Output** (`src/Input.php`, `src/Output.php`): Pipeline data containers

### Processing Pipeline
- **InputProcessorInterface**: Input transformation contract
- **OutputProcessorInterface**: Output transformation contract
- Middleware-like processing chain

### Key Features
- **Memory** (`src/Memory/`): Conversation memory with embeddings
- **Toolbox** (`src/Toolbox/`): Function calling capabilities
- **Structured Output**: Typed response support
- **Message Stores** (`src/Chat/MessageStore/`): Chat persistence

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
1. Input processors modify requests
2. Platform processes request
3. Output processors modify responses

### Built-in Processors
- **SystemPromptInputProcessor**: Adds system prompts
- **ModelOverrideInputProcessor**: Runtime model switching
- **MemoryInputProcessor**: Conversation context from memory

### Memory Providers
- **StaticMemoryProvider**: In-memory storage
- **EmbeddingProvider**: Vector-based semantic memory (requires Store)
- **MemoryBlockProvider**: Sleep-time enriched memory blocks (see Sleep-Time Agents)

### Sleep-Time Agents (`src/SleepTime/`)
Based on the [Sleep-time Compute paper](https://arxiv.org/html/2504.13171v1), sleep-time agents enrich shared memory by analyzing conversation history in the background.

**Architecture:**
- **SleepTimeAgent**: Wraps a primary agent and a sleeping agent. After every N calls, invokes the sleeping agent to process conversation context.
- **MemoryBlock**: Labeled, mutable memory block shared between both agents.
- **MemoryBlockProvider**: `MemoryProviderInterface` that injects memory block contents into the primary agent's context.
- **RethinkMemory** (tool): Used by the sleeping agent to update shared memory blocks with insights.

**Data flow:**
1. Primary agent handles the user query
2. After every N calls, the sleeping agent analyzes the conversation
3. The sleeping agent updates shared `MemoryBlock` instances via the `rethink_memory` tool
4. On subsequent calls, enriched memory is injected into the primary agent's context

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
- Prefer `self::assert*` in tests

## Development Notes

- Component is experimental (BC breaks possible)
- Add `@author` tags to new classes
- Use component-specific exceptions from `src/Exception/`
- Follow `@Symfony` PHP CS Fixer rules
