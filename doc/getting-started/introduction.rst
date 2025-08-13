Introduction
============

Symfony AI is a powerful suite of PHP components designed to seamlessly integrate artificial intelligence capabilities 
into Symfony applications. It provides developers with a unified, intuitive interface for working with various AI 
models and platforms while maintaining the high standards of code quality and developer experience that Symfony is 
known for.

Why Symfony AI?
---------------

The landscape of AI tools and services is rapidly evolving, with new models and providers emerging constantly. 
Symfony AI addresses several key challenges developers face when integrating AI into their applications:

**Unified Interface**
    Work with different AI providers (OpenAI, Anthropic, Google, etc.) through a consistent API, making it easy to 
    switch between providers or use multiple providers simultaneously.

**Type Safety**
    Leverage PHP's type system with fully typed interfaces, ensuring compile-time safety and excellent IDE support 
    for autocompletion and refactoring.

**Modular Architecture**
    Use only the components you need. Whether you want simple chat completions or complex agent workflows with 
    vector stores and RAG, Symfony AI scales with your requirements.

**Production Ready**
    Built with enterprise applications in mind, featuring proper error handling, logging, profiling, and testing 
    utilities out of the box.

**Symfony Integration**
    Deep integration with Symfony's dependency injection, configuration, event system, and developer tools like 
    the Profiler and Debug toolbar.

Key Features
------------

**Multi-Provider Support**
    Connect to OpenAI, Anthropic Claude, Google Gemini, Azure AI, AWS Bedrock, Mistral, Ollama, and many more 
    providers through a single interface.

**Agent Framework**
    Build sophisticated AI agents that can use tools, maintain conversation context, and execute complex workflows 
    with automatic retry and error handling.

**Vector Stores & RAG**
    Implement Retrieval Augmented Generation (RAG) with support for multiple vector databases including MongoDB, 
    Pinecone, Qdrant, MariaDB, and more.

**Multimodal Capabilities**
    Process text, images, audio, and documents. Generate images with DALL-E, transcribe audio with Whisper, and 
    analyze PDFs with vision models.

**Structured Output**
    Get predictable, type-safe responses from AI models by defining PHP classes or JSON schemas for structured output.

**Tool Calling**
    Enable AI models to interact with your application through tools - from simple functions to complex API 
    integrations and even other AI agents.

**Streaming Support**
    Stream responses in real-time for better user experience in chat applications, with built-in support for 
    Server-Sent Events (SSE).

**Memory Management**
    Add contextual memory to conversations, allowing models to recall past interactions and maintain coherent, 
    personalized dialogues.

**Testing Utilities**
    Comprehensive testing support with in-memory implementations, mock platforms, and fixtures for unit and 
    integration testing.

Component Overview
------------------

Symfony AI is organized into several focused components:

**Platform Component**
    The foundation that provides abstraction for different AI model providers and their specific APIs.

**Agent Component** 
    High-level framework for building AI agents with tool calling, memory, and workflow management capabilities.

**Store Component**
    Vector storage abstraction for implementing semantic search and RAG patterns with various database backends.

**MCP SDK**
    Implementation of the Model Context Protocol for building interoperable AI tools and services.

**AI Bundle**
    Symfony integration bundle that wires everything together with dependency injection, configuration, and 
    developer tools.

**MCP Bundle**
    Symfony integration for the MCP SDK, enabling your application to act as an MCP server or client.

Who Is This For?
----------------

Symfony AI is designed for:

* **Symfony Developers** looking to add AI capabilities to existing applications
* **PHP Developers** building AI-powered applications who want a robust, well-tested framework
* **Teams** requiring flexibility to switch between AI providers without rewriting code
* **Enterprises** needing production-ready AI integration with proper monitoring and debugging
* **Startups** wanting to quickly prototype and iterate on AI features

Getting Help
------------

* **Documentation**: You're reading it! This comprehensive guide covers everything from basic setup to advanced features
* **Examples**: The ``examples/`` directory contains working code for common use cases
* **Demo Application**: A full Symfony application demonstrating various AI features in ``demo/``
* **GitHub Issues**: Report bugs or request features at https://github.com/symfony/ai
* **Symfony Slack**: Join the discussion in the #ai channel

Next Steps
----------

Ready to get started? Head to the :doc:`installation` guide to set up Symfony AI in your project, or jump to 
:doc:`quick-start` for a hands-on introduction to the main features.