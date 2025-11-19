# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in the Agent component.

## Component Overview

The Agent component provides a framework for building AI agents that interact with users and perform tasks. It sits on top of the Platform component and optionally integrates with the Store component for memory capabilities.

## Architecture

The Agent component follows a processor-based architecture:

### Core Classes
- **Agent** (`src/Agent.php`): Main agent class that orchestrates input/output processing
- **AgentInterface** (`src/AgentInterface.php`): Contract for agent implementations
- **Chat** (`src/Chat.php`): High-level chat interface with conversation management
- **Input/Output** (`src/Input.php`, `src/Output.php`): Data containers for processing pipeline

### Processing Pipeline
- **InputProcessorInterface** (`src/InputProcessorInterface.php`): Contract for input processors
- **OutputProcessorInterface** (`src/OutputProcessorInterface.php`): Contract for output processors

### Key Features
- **Memory System** (`src/Memory/`): Conversation memory with embedding support
- **Toolbox** (`src/Toolbox/`): Tool integration for function calling capabilities
- **Structured Output** (`src/StructuredOutput/`): Support for typed responses
- **Message Stores** (`src/Chat/MessageStore/`): Persistence for chat conversations

## Development Commands

### Testing
```bash
# Run all tests
vendor/bin/phpunit

# Run specific test
vendor/bin/phpunit tests/AgentTest.php

# Run tests with coverage
vendor/bin/phpunit --coverage-html coverage/
```

### Code Quality
```bash
# Static analysis (from component directory)
vendor/bin/phpstan analyse

# Code style fixing (from monorepo root)
cd ../../.. && vendor/bin/php-cs-fixer fix src/agent/
```

## Component-Specific Architecture

### Input/Output Processing Chain
The agent uses a middleware-like pattern:
1. Input processors modify requests before sending to the platform
2. Platform processes the request  
3. Output processors modify responses before returning

### Built-in Processors
- **SystemPromptInputProcessor**: Adds system prompts to conversations
- **ModelOverrideInputProcessor**: Allows runtime model switching
- **MemoryInputProcessor**: Adds conversation context from memory providers

### Memory Providers
- **StaticMemoryProvider**: Simple in-memory storage
- **EmbeddingProvider**: Vector-based semantic memory using Store component

### Tool Integration
The Toolbox system enables function calling:
- Tools are auto-discovered via attributes
- Fault-tolerant execution with error handling
- Event system for tool lifecycle management

## Dependencies

The Agent component depends on:
- **Platform component**: Required for AI model communication
- **Store component**: Optional, for embedding-based memory
- **Symfony components**: HttpClient, Serializer, PropertyAccess, Clock

## Testing Patterns

- Use `MockHttpClient` for HTTP mocking instead of response mocking
- Test processors independently from the main Agent class
- Use fixtures from `/fixtures` for multimodal content testing
- Prefer `self::assert*` over `$this->assert*` in tests

## Development Notes

- All new classes should have `@author` tags
- Use component-specific exceptions from `src/Exception/`
- Follow Symfony coding standards with `@Symfony` PHP CS Fixer rules
- The component is marked as experimental and subject to BC breaks